<?php

namespace WebBlocks\Cms\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
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

    $user = User::query()
      ->where('email', str((string) $credentials['email'])->lower()->toString())
      ->first();

    if ($user instanceof User && ! $user->is_active) {
      throw ValidationException::withMessages([
        'email' => 'This account is inactive. Please contact an administrator.',
      ]);
    }

    if (! Auth::attempt($credentials, $request->boolean('remember'))) {
      throw ValidationException::withMessages([
        'email' => 'The provided credentials do not match our records.',
      ]);
    }

    $request->session()->regenerate();
    $request->user()?->forceFill(['last_login_at' => now()])->save();

    return redirect()->intended(route('admin.dashboard'));
  }

  public function destroy(Request $request): RedirectResponse
  {
    Auth::guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('webblocks.auth.login');
  }
}
