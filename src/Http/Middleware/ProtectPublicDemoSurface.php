<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Support\PublicDemo\PublicDemoGuard;

class ProtectPublicDemoSurface
{
  public function __construct(private readonly PublicDemoGuard $guard) {}

  public function handle(Request $request, Closure $next): Response
  {
    if (! $this->guard->isConfiguredFor($request)) {
      return $next($request);
    }

    if ($request->routeIs('sitemap', 'sitemap.page')) {
      abort(404);
    }

    if ($request->routeIs('webblocks.auth.password.*')) {
      abort(404);
    }

    if (! $request->isMethodSafe()) {
      abort(403, __('webblocks-cms::admin.public_demo.action_not_available'));
    }

    $response = $next($request);

    if ($request->routeIs('robots')) {
      $response->setContent("User-agent: *\nDisallow: /\n");
      $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
    }

    $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

    return $response;
  }
}
