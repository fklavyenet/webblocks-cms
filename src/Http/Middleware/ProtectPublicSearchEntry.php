<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Http\Requests\PublicSearchRequest;

final class ProtectPublicSearchEntry
{
  public function handle(Request $request, Closure $next): Response
  {
    if (! $request->boolean('cms_search')) {
      return $next($request);
    }
    app(PublicSearchRequest::class);
    $response = app(ThrottleRequests::class)->handle($request, $next, 'cms-public-search');
    $response->headers->set('Cache-Control', 'private, no-store');

    return $response;
  }
}
