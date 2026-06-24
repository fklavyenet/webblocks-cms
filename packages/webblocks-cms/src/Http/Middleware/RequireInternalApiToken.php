<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenAuthenticator;
use WebBlocks\Cms\Support\InternalContentApi\InternalApiResponseMetadata;

class RequireInternalApiToken
{
  public function __construct(
    private readonly CmsApiTokenAuthenticator $authenticator,
    private readonly InternalApiResponseMetadata $metadata,
  ) {}

  public function handle(Request $request, Closure $next): mixed
  {
    $providedToken = $this->resolveToken($request);

    $token = $this->authenticator->authenticate($providedToken, $request);

    if ($token === null) {
      return $this->error('invalid_internal_api_token', 'Invalid internal API token.', 401);
    }

    $request->attributes->set('cms_api_token', $token);

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
    return response()->json($this->metadata->merge([
      'ok' => false,
      'code' => $code,
      'message' => $message,
      'errors' => [
        [
          'path' => 'Authorization',
          'message' => $message,
        ],
      ],
    ]), $status);
  }
}
