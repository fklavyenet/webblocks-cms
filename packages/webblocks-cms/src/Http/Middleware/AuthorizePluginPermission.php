<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizePluginPermission
{
  public function handle(Request $request, Closure $next, string $permission): Response
  {
    if (! $request->user()) {
      return redirect()->guest(route('login'));
    }

    abort_unless($request->user()?->can($permission), 403);

    return $next($request);
  }
}
