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
      return $this->error('internal_api_disabled', 'Internal API is disabled.', 503);
    }

    $providedToken = $this->resolveToken($request);

    if ($providedToken === '' || ! hash_equals($configuredToken, $providedToken)) {
      return $this->error('invalid_internal_api_token', 'Invalid internal API token.', 401);
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

  private function error(string $code, string $message, int $status): JsonResponse
  {
    return response()->json([
      'ok' => false,
      'code' => $code,
      'message' => $message,
      'errors' => [
        [
          'path' => 'Authorization',
          'message' => $message,
        ],
      ],
    ], $status);
  }
}
