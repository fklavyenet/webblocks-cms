<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Policies\Plugins\PluginRecoveryPolicy;

class RequirePluginRecoveryAccess
{
  public function handle(Request $request, Closure $next): Response
  {
    if (! $request->user()) {
      return redirect()->route('admin.plugins.recovery.login');
    }
    abort_unless(app(PluginRecoveryPolicy::class)->manage($request->user()), 403);

    return $next($request);
  }
}
