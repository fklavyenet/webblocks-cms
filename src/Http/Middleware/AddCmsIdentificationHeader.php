<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Support\WebBlocks;

class AddCmsIdentificationHeader
{
  public function handle(Request $request, Closure $next): Response
  {
    $response = $next($request);

    if ((bool) config('webblocks-cms.public.send_powered_by_header', true)) {
      $response->headers->set('X-Powered-By', WebBlocks::name());
    }

    return $response;
  }
}
