<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\InternalContentApi\InternalApiResponseMetadata;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;

class InternalContentPlanController extends Controller
{
  public function __construct(
    private readonly InternalContentPlanService $plans,
    private readonly InternalApiResponseMetadata $metadata,
    private readonly CmsApiTokenCapabilities $capabilities,
  ) {}

  public function validatePlan(Request $request): JsonResponse
  {
    $result = $this->plans->validate($request->json()->all());

    $payload = $result->toArray();

    return response()->json($result->ok ? $payload : $this->metadata->merge($payload), $result->ok ? 200 : 422);
  }

  public function apply(Request $request): JsonResponse
  {
    if ($this->requiresPublishCapability($request) && ! $this->hasPublishCapability($request)) {
      return response()->json($this->metadata->merge([
        'ok' => false,
        'code' => 'missing_internal_api_capability',
        'message' => 'The API token does not have the required capability.',
        'required_capability' => CmsApiTokenCapabilities::CONTENT_PUBLISH,
        'errors' => [
          [
            'path' => 'Authorization',
            'message' => 'Promoting a staged update onto a published page requires content.publish.',
          ],
        ],
      ]), 403);
    }

    $result = $this->plans->apply($request->json()->all());

    $payload = $result->toArray();

    if ($result->ok) {
      Log::info('Internal Content API apply completed.', [
        'writes' => collect($payload['writes'] ?? [])->pluck('type')->countBy()->all(),
        'token_id' => $request->attributes->get('cms_api_token')?->id,
      ]);
    }

    return response()->json($result->ok ? $payload : $this->metadata->merge($payload), $result->ok ? 201 : 422);
  }

  private function requiresPublishCapability(Request $request): bool
  {
    $payload = $request->json()->all();
    $plan = is_array($payload['plan'] ?? null) ? $payload['plan'] : $payload;

    return ($plan['mode'] ?? null) === InternalContentPlanService::MODE_PROMOTE_STAGED_UPDATE;
  }

  private function hasPublishCapability(Request $request): bool
  {
    $token = $request->attributes->get('cms_api_token');

    return $token instanceof CmsApiToken
      && $this->capabilities->has($token, CmsApiTokenCapabilities::CONTENT_PUBLISH);
  }
}
