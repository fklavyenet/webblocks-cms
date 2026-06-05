<?php

namespace WebBlocks\Cms\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
  public function create(Request $request): View
  {
    return view('webblocks-cms::auth.reset-password', ['request' => $request]);
  }

  /**
     * @throws ValidationException
     */
  public function store(Request $request): RedirectResponse
  {
    $request->validate([
      'token' => ['required'],
      'email' => ['required', 'email'],
      'password' => ['required', 'confirmed', Rules\Password::defaults()],
    ]);

    $status = Password::reset(
      $request->only('email', 'password', 'password_confirmation', 'token'),
      function (User $user) use ($request) {
        $user->forceFill([
          'password' => Hash::make($request->password),
          'remember_token' => Str::random(60),
        ])->save();

        event(new PasswordReset($user));
      }
    );

    return $status == Password::PASSWORD_RESET
      ? redirect()->route('login')->with('status', __($status))
      : back()->withInput($request->only('email'))
        ->withErrors(['email' => __($status)]);
  }
}
