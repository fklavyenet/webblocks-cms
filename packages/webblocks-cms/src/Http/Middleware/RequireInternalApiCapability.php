<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\InternalContentApi\InternalApiResponseMetadata;

class RequireInternalApiCapability
{
  public function __construct(
    private readonly CmsApiTokenCapabilities $capabilities,
    private readonly InternalApiResponseMetadata $metadata,
  ) {}

  public function handle(Request $request, Closure $next, string $capability): mixed
  {
    $token = $request->attributes->get('cms_api_token');

    if (! $token instanceof CmsApiToken || ! $this->capabilities->has($token, $capability)) {
      return $this->error($capability);
    }

    return $next($request);
  }

  private function error(string $capability): JsonResponse
  {
    return response()->json($this->metadata->merge([
      'ok' => false,
      'code' => 'missing_internal_api_capability',
      'message' => 'The API token does not have the required capability.',
      'required_capability' => $capability,
      'errors' => [
        [
          'path' => 'Authorization',
          'message' => 'The API token does not have the required capability.',
        ],
      ],
    ]), 403);
  }
}
