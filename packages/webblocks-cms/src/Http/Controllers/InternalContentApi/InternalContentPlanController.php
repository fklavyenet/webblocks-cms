<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use WebBlocks\Cms\Support\InternalContentApi\InternalApiResponseMetadata;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;

class InternalContentPlanController extends Controller
{
  public function __construct(
    private readonly InternalContentPlanService $plans,
    private readonly InternalApiResponseMetadata $metadata,
  ) {}

  public function validatePlan(Request $request): JsonResponse
  {
    $result = $this->plans->validate($request->json()->all());

    $payload = $result->toArray();

    return response()->json($result->ok ? $payload : $this->metadata->merge($payload), $result->ok ? 200 : 422);
  }

  public function apply(Request $request): JsonResponse
  {
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
}
