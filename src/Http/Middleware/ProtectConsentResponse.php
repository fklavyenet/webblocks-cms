<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProtectConsentResponse
{
  public function handle(Request $request, Closure $next): Response
  {
    $response = $next($request);
    if ($request->attributes->get('cms.consent.rendered')) {
      $response->headers->set('Cache-Control', 'private, no-store');
      $response->setVary('Cookie', false);
    }

    return $response;
  }
}
