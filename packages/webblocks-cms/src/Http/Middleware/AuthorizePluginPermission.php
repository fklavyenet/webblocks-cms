<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Support\Plugins\PluginAccessResolver;
use WebBlocks\Cms\Support\Plugins\PluginAuthorizationRegistrar;

class AuthorizePluginPermission
{
  public function __construct(
    private readonly PluginAuthorizationRegistrar $authorization,
    private readonly PluginAccessResolver $access,
  ) {}

  public function handle(Request $request, Closure $next, string $permission): Response
  {
    $this->authorization->register();

    if (! $request->user()) {
      return redirect()->guest(route('webblocks.auth.login'));
    }

    abort_unless($this->access->canAccessPluginPermission($request->user(), $permission), 403);

    return $next($request);
  }
}
