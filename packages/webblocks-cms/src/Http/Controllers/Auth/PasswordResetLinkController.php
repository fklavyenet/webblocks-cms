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

    if (! $user instanceof User) {
      return back()->withInput($request->only('email'))
        ->withErrors(['email' => __(Password::INVALID_USER)]);
    }

    try {
      $user->notify(new CmsResetPassword($broker->createToken($user), $request->email));
    } catch (Throwable $exception) {
      Log::warning('CMS password reset email could not be sent.', $this->mailSettingsResolver->logContext() + [
        'exception_class' => $exception::class,
        'sanitized_message' => $this->mailSettingsResolver->sanitizedExceptionMessage($exception),
      ]);

      return back()->withInput($request->only('email'))
        ->withErrors(['email' => self::MAIL_FAILURE_MESSAGE]);
    }

    return back()->with('status', __(Password::RESET_LINK_SENT));
  }
}
