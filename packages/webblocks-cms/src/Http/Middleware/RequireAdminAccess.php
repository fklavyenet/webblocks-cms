<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminAccess
{
  public function handle(Request $request, Closure $next): Response
  {
    $user = $request->user();

    if (! $user || ! method_exists($user, 'canAccessAdmin') || ! $user->canAccessAdmin()) {
      abort(403);
    }

    return $next($request);
  }
}
