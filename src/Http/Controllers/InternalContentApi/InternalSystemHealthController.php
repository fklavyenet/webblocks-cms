<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Http\Requests\Admin\SystemHealthRequest;
use WebBlocks\Cms\Queries\SystemHealthQuery;

class InternalSystemHealthController extends Controller
{
  public function show(SystemHealthRequest $request, SystemHealthQuery $health): JsonResponse
  {
    return response()->json(['ok' => true, 'health' => $health->report($request->siteId())]);
  }
}
