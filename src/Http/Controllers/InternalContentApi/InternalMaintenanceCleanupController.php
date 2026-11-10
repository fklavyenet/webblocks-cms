<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Http\Requests\InternalContentApi\MaintenanceCleanupSettingsRequest;
use WebBlocks\Cms\Support\System\CleanupSettingsFields;
use WebBlocks\Cms\Support\System\MaintenanceCleanup;
use WebBlocks\Cms\Support\System\MaintenanceCleanupResult;
use WebBlocks\Cms\Support\System\SystemSettings;

class InternalMaintenanceCleanupController extends Controller
{
  public function __construct(private readonly MaintenanceCleanup $cleanup, private readonly SystemSettings $settings) {}

  public function show(): JsonResponse
  {
    $overview = $this->cleanup->overview();
    foreach ($overview as $key => $value) {
      if ($value instanceof MaintenanceCleanupResult) {
        $overview[$key] = $this->present($value);
      }
    }

    return response()->json(['ok' => true, 'policy' => [...$this->settings->maintenanceCleanupSettings(), 'automatic' => $this->settings->automaticMaintenanceSettings()], 'overview' => $overview]);
  }

  public function run(string $category): JsonResponse
  {
    abort_unless(in_array($category, MaintenanceCleanup::RUNNABLE, true), 404);

    $result = $this->cleanup->run($category);

    return response()->json(['ok' => $result->failures === [], 'category' => $category, 'result' => $this->present($result)]);
  }

  public function update(MaintenanceCleanupSettingsRequest $request): JsonResponse
  {
    $this->settings->save(CleanupSettingsFields::maintenancePayload($request->validated()));

    return $this->show();
  }

  private function present(MaintenanceCleanupResult $result): array
  {
    return ['candidate_count' => $result->candidateCount, 'candidate_bytes' => $result->candidateBytes, 'deleted_count' => $result->deletedCount, 'deleted_bytes' => $result->deletedBytes, 'failure_count' => count($result->failures)];
  }
}
