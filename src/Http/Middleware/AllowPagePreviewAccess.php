<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenActivityLogger;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenAuthenticator;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\InternalContentApi\InternalApiResponseMetadata;

class AllowPagePreviewAccess
{
  public function __construct(
    private readonly CmsApiTokenAuthenticator $authenticator,
    private readonly CmsApiTokenActivityLogger $activityLogger,
    private readonly CmsApiTokenCapabilities $capabilities,
    private readonly InternalApiResponseMetadata $metadata,
  ) {}

  public function handle(Request $request, Closure $next): Response
  {
    $user = $request->user();

    if ($user) {
      if (! method_exists($user, 'canAccessAdmin') || ! $user->canAccessAdmin()) {
        abort(403);
      }

      return $next($request);
    }

    if ((string) $request->bearerToken() === '') {
      return redirect()->guest($this->loginUrl());
    }

    $token = $this->authenticator->authenticate((string) $request->bearerToken(), $request);

    if (! $token instanceof CmsApiToken) {
      return $this->error('invalid_internal_api_token', 'Invalid internal API token.', 401);
    }

    if (! $this->capabilities->has($token, CmsApiTokenCapabilities::CONTENT_READ)) {
      $this->activityLogger->capabilityDenied($token, $request, CmsApiTokenCapabilities::CONTENT_READ);

      return $this->error(
        'missing_internal_api_capability',
        'The API token does not have the required capability.',
        403,
        CmsApiTokenCapabilities::CONTENT_READ,
      );
    }

    $this->activityLogger->capabilityAllowed($token, $request, CmsApiTokenCapabilities::CONTENT_READ);
    $request->attributes->set('cms_api_token', $token);
    $request->attributes->set('cms_internal_preview', true);

    return $next($request);
  }

  private function loginUrl(): string
  {
    return Route::has('webblocks.auth.login')
      ? route('webblocks.auth.login')
      : '/webadmin/login';
  }

  private function error(string $code, string $message, int $status, ?string $capability = null): JsonResponse
  {
    return response()->json($this->metadata->merge(array_filter([
      'ok' => false,
      'code' => $code,
      'message' => $message,
      'required_capability' => $capability,
      'errors' => [
        [
          'path' => 'Authorization',
          'message' => $message,
        ],
      ],
    ], fn ($value) => $value !== null)), $status);
  }
}
