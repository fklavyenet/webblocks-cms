<?php

namespace WebBlocks\Cms\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

class LoginController extends Controller
{
  public function __construct(
    private readonly AdminLocaleResolver $localeResolver,
    private readonly CmsTranslator $translator,
  ) {}

  public function create(): View
  {
    return view('webblocks-cms::auth.login');
  }

  public function store(Request $request): RedirectResponse
  {
    $credentials = $request->validate([
      'email' => ['required', 'email'],
      'password' => ['required', 'string'],
    ]);

    $this->ensureIsNotRateLimited($request);

    $user = User::query()
      ->where('email', str((string) $credentials['email'])->lower()->toString())
      ->first();

    if ($user instanceof User && ! $user->is_active) {
      RateLimiter::hit($this->throttleKey($request), $this->decaySeconds());

      throw ValidationException::withMessages([
        'email' => $this->translator->admin('auth.inactive_account', $this->localeResolver->locale()),
      ]);
    }

    if (! Auth::attempt($credentials, $request->boolean('remember'))) {
      RateLimiter::hit($this->throttleKey($request), $this->decaySeconds());

      throw ValidationException::withMessages([
        'email' => $this->translator->admin('auth.invalid_credentials', $this->localeResolver->locale()),
      ]);
    }

    RateLimiter::clear($this->throttleKey($request));

    $request->session()->regenerate();
    $request->user()?->forceFill(['last_login_at' => now()])->save();

    return redirect()->intended(route('admin.dashboard'));
  }

  private function ensureIsNotRateLimited(Request $request): void
  {
    if (! RateLimiter::tooManyAttempts($this->throttleKey($request), $this->maxAttempts())) {
      return;
    }

    $seconds = RateLimiter::availableIn($this->throttleKey($request));

    throw ValidationException::withMessages([
      'email' => $this->translator->admin('auth.throttled', $this->localeResolver->locale(), [
        'seconds' => $seconds,
        'minutes' => (int) ceil($seconds / 60),
      ]),
    ]);
  }

  private function throttleKey(Request $request): string
  {
    return Str::transliterate(
      Str::lower((string) $request->input('email')).'|'.$request->ip()
    );
  }

  private function maxAttempts(): int
  {
    return max(1, (int) config('webblocks-cms.auth.max_login_attempts', 5));
  }

  private function decaySeconds(): int
  {
    return max(1, (int) config('webblocks-cms.auth.login_decay_seconds', 60));
  }

  public function destroy(Request $request): RedirectResponse
  {
    Auth::guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('webblocks.auth.login');
  }
}
