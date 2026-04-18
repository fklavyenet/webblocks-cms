<?php

namespace App\Http\Middleware;

use App\Support\System\Updates\UpdateApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUpdateServerEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('webblocks-updates.enabled', true) || ! config('webblocks-updates.server.enabled', true)) {
            return UpdateApiResponse::error('update_server_disabled', 'The update server is disabled.', 404);
        }

        return $next($request);
    }
}
