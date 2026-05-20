<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Support\Install\InstallState;

class RedirectIfNotInstalled
{
    public function __construct(
        private readonly InstallState $installState,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->installState->guardsEnabled() || $this->installState->isInstalled()) {
            return $next($request);
        }

        if ($request->routeIs('webblocks-cms.install.*')) {
            return $next($request);
        }

        return redirect()->route('webblocks-cms.install.notice');
    }
}
