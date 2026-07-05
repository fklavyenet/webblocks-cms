<?php

namespace WebBlocks\Cms\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;
use WebBlocks\Cms\Notifications\Auth\CmsResetPassword;
use WebBlocks\Cms\Support\Mail\CmsMailSettingsResolver;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

class PasswordResetLinkController extends Controller
{
  public function __construct(
    private readonly CmsMailSettingsResolver $mailSettingsResolver,
    private readonly AdminLocaleResolver $localeResolver,
    private readonly CmsTranslator $translator,
  ) {}

  public function create(): View
  {
    return view('webblocks-cms::auth.forgot-password');
  }

  /**
   * @throws ValidationException
   */
  public function store(Request $request): RedirectResponse
  {
    $request->validate([
      'email' => ['required', 'email'],
    ]);

    $broker = Password::broker();
    $user = $broker->getUser($request->only('email'));

    if (! $user instanceof User || ! $this->userCanReceivePasswordReset($user)) {
      return back()
        ->withInput($request->only('email'))
        ->with('status', $this->authText('reset_link_sent'));
    }

    try {
      $user->notify(new CmsResetPassword($broker->createToken($user), $request->email));
    } catch (Throwable $exception) {
      Log::warning('CMS password reset email could not be sent.', $this->mailSettingsResolver->logContext() + $this->passwordResetLogContext($user) + [
        'exception_class' => $exception::class,
        'sanitized_message' => $this->mailSettingsResolver->sanitizedExceptionMessage($exception),
      ]);

      return back()->withInput($request->only('email'))
        ->withErrors(['email' => $this->authText('reset_failed')]);
    }

    return back()->with('status', $this->authText('reset_link_sent'));
  }

  private function authText(string $key): string
  {
    return $this->translator->admin('auth.'.$key, $this->localeResolver->locale());
  }

  private function userCanReceivePasswordReset(User $user): bool
  {
    return (bool) ($user->is_active ?? true);
  }

  /**
   * @return array<string, mixed>
   */
  private function passwordResetLogContext(User $user): array
  {
    $context = [
      'reset_route_name' => CmsResetPassword::RESET_ROUTE_NAME,
      'user_found' => true,
      'user_active' => $this->userCanReceivePasswordReset($user),
      'notifiable_class' => $user::class,
    ];

    try {
      $url = route(CmsResetPassword::RESET_ROUTE_NAME, [
        'token' => '[redacted-token]',
        'email' => '[redacted-email]',
      ]);
      $parts = parse_url($url) ?: [];

      $context['reset_url_host'] = $parts['host'] ?? null;
      $context['reset_url_path'] = $parts['path'] ?? null;
    } catch (Throwable $exception) {
      $context['reset_url_error_class'] = $exception::class;
      $context['reset_url_error'] = $this->mailSettingsResolver->sanitizedExceptionMessage($exception);
    }

    return $context;
  }
}
