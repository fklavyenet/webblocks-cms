<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventAdministrativeIndexing
{
  public function handle(Request $request, Closure $next): Response
  {
    $response = $next($request);

    if ($request->is('webadmin', 'webadmin/*')) {
      $existing = $response->headers->all('X-Robots-Tag');
      $hasGeneralNoindex = false;
      foreach ($existing as $header) {
        if (preg_match('/(?:^|,)\s*noindex\s*(?:,|$)/i', explode(':', $header, 2)[0])) {
          $hasGeneralNoindex = true;
          break;
        }
      }
      if (! $hasGeneralNoindex) {
        $response->headers->set('X-Robots-Tag', ['noindex', ...$existing]);
      }
    }

    return $response;
  }
}
