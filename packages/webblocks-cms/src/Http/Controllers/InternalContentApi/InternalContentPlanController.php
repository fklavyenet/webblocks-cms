<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;

class InternalContentPlanController extends Controller
{
  public function __construct(
    private readonly InternalContentPlanService $plans,
  ) {}

  public function validatePlan(Request $request): JsonResponse
  {
    $result = $this->plans->validate($request->json()->all());

    return response()->json($result->toArray(), $result->ok ? 200 : 422);
  }

  public function apply(Request $request): JsonResponse
  {
    $result = $this->plans->apply($request->json()->all());

    return response()->json($result->toArray(), $result->ok ? 201 : 422);
  }
}
