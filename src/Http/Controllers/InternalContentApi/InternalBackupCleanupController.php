<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Http\Requests\InternalContentApi\BackupCleanupSettingsRequest;
use WebBlocks\Cms\Support\System\BackupRetentionSettings;
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
    $validated = $request->validated();

    $this->settings->save([
      ...BackupRetentionSettings::payload($validated),
      SystemSettings::BACKUP_CLEANUP_ENABLED => $validated['enabled'],
      SystemSettings::BACKUP_CLEANUP_PRE_UPDATE_DAYS => $validated['pre_update_days'],
      SystemSettings::BACKUP_CLEANUP_KEEP_LATEST_PRE_UPDATE => $validated['keep_latest_pre_update'],
      SystemSettings::BACKUP_CLEANUP_RESTORE_SAFETY_DAYS => $validated['restore_safety_days'],
      SystemSettings::BACKUP_CLEANUP_CONTENT_APPLY_DAYS => $validated['content_apply_days'],
    ]);

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
