<?php

namespace App\Http\Middleware;

use App\Support\Install\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfInstalled
{
    public function __construct(
        private readonly InstallState $installState,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->installState->guardsEnabled() || ! $this->installState->isInstalled()) {
            return $next($request);
        }

        if ($request->routeIs('install.finish') && $request->session()->get('install.finish_available')) {
            return $next($request);
        }

        return redirect()->route($request->user() ? 'admin.dashboard' : 'login');
    }
}
