<?php

namespace App\Http\Middleware;

use App\Support\Install\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        return redirect()->route($this->installState->nextIncompleteStepRouteName());
    }
}
