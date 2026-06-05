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

class PasswordResetLinkController extends Controller
{
  private const MAIL_FAILURE_MESSAGE = 'The password reset email could not be sent. Please check CMS Mail settings or contact an administrator.';

  public function __construct(
    private readonly CmsMailSettingsResolver $mailSettingsResolver,
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
        ->with('status', __(Password::RESET_LINK_SENT));
    }

    try {
      $user->notify(new CmsResetPassword($broker->createToken($user), $request->email));
    } catch (Throwable $exception) {
      Log::warning('CMS password reset email could not be sent.', $this->mailSettingsResolver->logContext() + $this->passwordResetLogContext($user) + [
        'exception_class' => $exception::class,
        'sanitized_message' => $this->mailSettingsResolver->sanitizedExceptionMessage($exception),
      ]);

      return back()->withInput($request->only('email'))
        ->withErrors(['email' => self::MAIL_FAILURE_MESSAGE]);
    }

    return back()->with('status', __(Password::RESET_LINK_SENT));
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
