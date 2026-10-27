<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Support\Auth\TwoFactorAccountState;

final class RevokeResetCmsSessions
{
  public function handle(Request $request, Closure $next): Response
  {
    // Bearer-token transports retain their independent authentication boundary.
    if ($request->routeIs('internal-content-api.*', 'admin-api.*')) {
      return $next($request);
    }
    $user = $request->user('web');
    if (! $user || app(TwoFactorAccountState::class)->current($request, $user)) {
      return $next($request);
    }
    Auth::guard('web')->logoutCurrentDevice();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return $request->expectsJson()
      ? response()->json(['code' => 'cms_session_revoked', 'message' => __('webblocks-cms::two-factor.session_revoked')], 401)
      : to_route('webblocks.auth.login')->with('status', __('webblocks-cms::two-factor.session_revoked'));
  }
}
