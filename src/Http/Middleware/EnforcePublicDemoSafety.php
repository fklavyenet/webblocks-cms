<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Support\PublicDemo\PublicDemoGuard;

class EnforcePublicDemoSafety
{
  public function __construct(private readonly PublicDemoGuard $guard) {}

  public function handle(Request $request, Closure $next): Response
  {
    if (! $this->guard->isConfiguredFor($request)) {
      return $next($request);
    }

    if ($this->guard->isDemoUser($request->user()) && ! $this->guard->permits($request)) {
      abort(403, __('webblocks-cms::admin.public_demo.action_not_available'));
    }

    $response = $next($request);
    $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

    return $response;
  }
}
