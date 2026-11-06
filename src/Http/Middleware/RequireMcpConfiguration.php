<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Support\Mcp\McpConfiguration;

final class RequireMcpConfiguration
{
  public function handle(Request $request, Closure $next): Response
  {
    app(McpConfiguration::class)->assertAvailable($request);
    $response = $next($request);
    $response->headers->set('Cache-Control', 'no-store, private');
    $response->headers->set('Referrer-Policy', 'no-referrer');
    $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

    return $response;
  }
}
