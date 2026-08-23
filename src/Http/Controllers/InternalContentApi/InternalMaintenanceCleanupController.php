<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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

    return response()->json(['ok' => true, 'policy' => $this->settings->maintenanceCleanupSettings(), 'overview' => $overview]);
  }

  public function run(string $category): JsonResponse
  {
    abort_unless(in_array($category, MaintenanceCleanup::RUNNABLE, true), 404);

    return response()->json(['ok' => true, 'category' => $category, 'result' => $this->present($this->cleanup->run($category))]);
  }

  public function update(Request $request): JsonResponse
  {
    $validated = validator($request->all(), [
      'asset_revision_days' => ['required', 'integer', 'min:1', 'max:3650'],
      'keep_latest_asset_revisions' => ['required', 'integer', 'min:1', 'max:1000'],
      'temporary_workspace_hours' => ['required', 'integer', 'min:1', 'max:8760'],
    ])->validate();
    $this->settings->save([
      SystemSettings::CLEANUP_ASSET_REVISION_DAYS => $validated['asset_revision_days'],
      SystemSettings::CLEANUP_KEEP_LATEST_ASSET_REVISIONS => $validated['keep_latest_asset_revisions'],
      SystemSettings::CLEANUP_TEMPORARY_WORKSPACE_HOURS => $validated['temporary_workspace_hours'],
    ]);

    return $this->show();
  }

  private function present(MaintenanceCleanupResult $result): array
  {
    return ['candidate_count' => $result->candidateCount, 'candidate_bytes' => $result->candidateBytes, 'deleted_count' => $result->deletedCount, 'deleted_bytes' => $result->deletedBytes, 'failure_count' => count($result->failures)];
  }
}
