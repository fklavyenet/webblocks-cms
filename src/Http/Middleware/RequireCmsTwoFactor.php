<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Policies\TwoFactorPolicy;
use WebBlocks\Cms\Support\Auth\TwoFactorAuthentication;
use WebBlocks\Cms\Support\Auth\TwoFactorSession;

final class RequireCmsTwoFactor
{
  public function handle(Request $request, Closure $next): Response
  {
    if (! $request->is('webadmin', 'webadmin/*')) {
      return $next($request);
    }
    // These routes authenticate bearer tokens and enforce capabilities separately.
    if ($request->routeIs('internal-content-api.*', 'admin-api.*')) {
      return $next($request);
    }
    $securityRoute = str_starts_with((string) $request->route()?->getName(), 'webblocks.two-factor.');
    if ($securityRoute || $request->routeIs('webblocks.auth.logout')) {
      $response = $next($request);
      if ($securityRoute) {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');
      }

      return $response;
    }
    $user = $request->user('web');
    if (! app(TwoFactorPolicy::class)->manage($user)) {
      return $next($request);
    }
    $auth = app(TwoFactorAuthentication::class);
    $credential = $auth->credential($user);
    $confirmed = $credential?->confirmed_at !== null;
    if ((! $confirmed && ! $auth->required($user)) || ($confirmed && app(TwoFactorSession::class)->verified($request, $user, $credential))) {
      return $next($request);
    }
    if ($request->is('webadmin/plugin-recovery', 'webadmin/plugin-recovery/*')) {
      $request->session()->put(TwoFactorSession::RECOVERY, true);
    }
    $url = route($confirmed ? 'webblocks.two-factor.challenge' : 'webblocks.two-factor.show');
    if ($request->expectsJson()) {
      return response()->json(['code' => 'cms_two_factor_required', 'redirect' => $url], 423);
    }
    if ($request->isMethod('GET') && ! $request->routeIs('webblocks.auth.*')) {
      $request->session()->put('url.intended', $request->fullUrl());
    }

    return redirect()->to($url);
  }
}
