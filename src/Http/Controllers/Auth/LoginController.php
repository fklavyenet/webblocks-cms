<?php

namespace WebBlocks\Cms\Http\Controllers\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use WebBlocks\Cms\Support\Auth\TwoFactorAuthentication;
use WebBlocks\Cms\Support\Auth\TwoFactorSession;
use WebBlocks\Cms\Support\Plugins\PluginRecoveryMode;
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
    return view('webblocks-cms::auth.login', ['pluginRecoveryMode' => app(PluginRecoveryMode::class)->active()]);
  }

  public function store(Request $request): RedirectResponse
  {
    $credentials = $request->validate([
      'email' => ['required', 'email'],
      'password' => ['required', 'string'],
    ]);

    $this->ensureIsNotRateLimited($request);

    $credentials['email'] = str((string) $credentials['email'])->lower()->toString();
    $user = Auth::guard('web')->getProvider()->retrieveByCredentials($credentials);

    if ($user instanceof Authenticatable && ! $user->is_active) {
      RateLimiter::hit($this->throttleKey($request), $this->decaySeconds());

      throw ValidationException::withMessages([
        'email' => $this->translator->admin('auth.inactive_account', $this->localeResolver->locale()),
      ]);
    }

    $requiresChallenge = $user instanceof Authenticatable && app(TwoFactorAuthentication::class)->credential($user)?->confirmed_at !== null;
    $guard = Auth::guard('web');
    $valid = $requiresChallenge ? $guard->validate($credentials) : $guard->attempt($credentials, $request->boolean('remember'));

    if (! $valid) {
      RateLimiter::hit($this->throttleKey($request), $this->decaySeconds());

      throw ValidationException::withMessages([
        'email' => $this->translator->admin('auth.invalid_credentials', $this->localeResolver->locale()),
      ]);
    }

    RateLimiter::clear($this->throttleKey($request));

    if ($requiresChallenge) {
      $guard->getProvider()->rehashPasswordIfRequired($user, $credentials);
      app(TwoFactorSession::class)->begin($request, $user, $request->boolean('remember'));

      return to_route('webblocks.two-factor.challenge');
    }

    $request->session()->regenerate();
    $request->user()?->forceFill(['last_login_at' => now()])->save();

    if (app(PluginRecoveryMode::class)->active()) {
      $request->session()->forget('url.intended');

      return to_route('admin.plugins.recovery.index');
    }

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
