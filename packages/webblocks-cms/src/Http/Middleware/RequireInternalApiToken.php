<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequireInternalApiToken
{
  public function handle(Request $request, Closure $next): mixed
  {
    $configuredToken = trim((string) env('WEBBLOCKS_CMS_INTERNAL_API_TOKEN', ''));

    if ($configuredToken === '') {
      return $this->unauthorized('Internal API is disabled.');
    }

    $providedToken = $this->resolveToken($request);

    if ($providedToken === '' || ! hash_equals($configuredToken, $providedToken)) {
      return $this->unauthorized('Invalid internal API token.');
    }

    return $next($request);
  }

  private function resolveToken(Request $request): string
  {
    $bearer = (string) $request->bearerToken();

    if ($bearer !== '') {
      return $bearer;
    }

    $documentedHeader = trim((string) $request->header('X-WebBlocks-Internal-Token', ''));

    if ($documentedHeader !== '') {
      return $documentedHeader;
    }

    return trim((string) $request->header('X-WebBlocks-Internal-Api-Token', ''));
  }

  private function unauthorized(string $message): JsonResponse
  {
    return response()->json(['message' => $message], 401);
  }
}
