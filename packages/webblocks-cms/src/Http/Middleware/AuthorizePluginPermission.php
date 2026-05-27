<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Support\Plugins\PluginAuthorizationRegistrar;

class AuthorizePluginPermission
{
  public function __construct(
    private readonly PluginAuthorizationRegistrar $authorization,
  ) {}

  public function handle(Request $request, Closure $next, string $permission): Response
  {
    $this->authorization->register();

    if (! $request->user()) {
      return redirect()->guest(route('login'));
    }

    abort_unless($request->user()?->can($permission), 403);

    return $next($request);
  }
}
