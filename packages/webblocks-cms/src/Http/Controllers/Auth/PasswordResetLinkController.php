<?php

namespace WebBlocks\Cms\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use WebBlocks\Cms\Notifications\Auth\CmsResetPassword;

class PasswordResetLinkController extends Controller
{
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

    $user->notify(new CmsResetPassword($broker->createToken($user), $request->email));

    return back()->with('status', __(Password::RESET_LINK_SENT));
  }
}
