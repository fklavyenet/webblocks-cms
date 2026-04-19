<?php

namespace App\Http\Middleware;

use App\Support\System\Updates\UpdateApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReleasePublishAuthorized
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('webblocks-release.publish.token', '');

        if ($token === '' || $request->bearerToken() !== $token) {
            return UpdateApiResponse::error('unauthorized', 'A valid release publish token is required.', 401);
        }

        return $next($request);
    }
}
