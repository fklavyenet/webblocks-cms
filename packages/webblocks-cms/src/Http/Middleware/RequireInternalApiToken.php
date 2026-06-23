<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenAuthenticator;

class RequireInternalApiToken
{
  public function __construct(private readonly CmsApiTokenAuthenticator $authenticator) {}

  public function handle(Request $request, Closure $next): mixed
  {
    $providedToken = $this->resolveToken($request);

    if ($this->authenticator->authenticate($providedToken, $request) === null) {
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

    return '';
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
