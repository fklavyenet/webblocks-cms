<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Http\Requests\InternalContentApi\BackupCleanupSettingsRequest;
use WebBlocks\Cms\Support\System\CleanupSettingsFields;
use WebBlocks\Cms\Support\System\SystemBackupCleanup;
use WebBlocks\Cms\Support\System\SystemSettings;

class InternalBackupCleanupController extends Controller
{
  public function __construct(
    private readonly SystemSettings $settings,
    private readonly SystemBackupCleanup $cleanup,
  ) {}

  public function show(): JsonResponse
  {
    return response()->json(['ok' => true, 'policy' => $this->settings->backupCleanupSettings(), 'preview' => $this->present($this->cleanup->preview())]);
  }

  public function update(BackupCleanupSettingsRequest $request): JsonResponse
  {
    $this->settings->save(CleanupSettingsFields::backupPayload($request->validated()));

    return $this->show();
  }

  public function run(): JsonResponse
  {
    $result = $this->cleanup->run(force: true);

    return response()->json(['ok' => $result->failures === [], 'result' => $this->present($result)]);
  }

  private function present($result): array
  {
    return [
      'overview' => $result->overview,
      'candidate_count' => $result->candidateCount(),
      'candidate_bytes' => $result->candidateBytes,
      'deleted_count' => $result->deletedCount(),
      'deleted_bytes' => $result->deletedBytes,
      'failure_count' => count($result->failures),
    ];
  }
}
