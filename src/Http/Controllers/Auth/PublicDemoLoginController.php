<?php

namespace WebBlocks\Cms\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use WebBlocks\Cms\Support\PublicDemo\PublicDemoGuard;

class PublicDemoLoginController extends Controller
{
  public function __invoke(Request $request, PublicDemoGuard $guard): RedirectResponse
  {
    abort_unless($guard->isConfiguredFor($request), 404);

    $email = trim((string) config('webblocks-cms.public_demo.user_email'));
    $user = User::query()->where('email', $email)->first();

    abort_unless($user instanceof User && $guard->isDemoUser($user) && $user->canAccessAdmin(), 503);

    Auth::guard((string) config('webblocks-cms.auth.guard', 'web'))->login($user);
    $request->session()->regenerate();
    $user->forceFill(['last_login_at' => now()])->save();

    return redirect()->route('admin.dashboard');
  }
}
