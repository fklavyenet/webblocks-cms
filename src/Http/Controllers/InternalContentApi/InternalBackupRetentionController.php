<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Actions\SystemBackups\UpdateBackupRetention;
use WebBlocks\Cms\Http\Requests\InternalContentApi\BackupRetentionRequest;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Support\System\BackupOperationException;
use WebBlocks\Cms\Support\System\BackupRetentionPlan;

final class InternalBackupRetentionController extends Controller
{
  public function show(SystemBackup $backup): JsonResponse
  {
    return response()->json(['ok' => true, 'backup_id' => $backup->id, 'is_protected' => (bool) $backup->is_protected,
      'retention_mode' => $backup->retention_mode ?? 'policy', 'expires_at' => $backup->expires_at?->toIso8601String(),
      'decision' => collect(app(BackupRetentionPlan::class)->overview()['items'])->firstWhere('id', $backup->id)]);
  }

  public function update(BackupRetentionRequest $request, SystemBackup $backup, UpdateBackupRetention $action): JsonResponse
  {
    try {
      return $this->show($action->execute($backup, $request->validated()));
    } catch (BackupOperationException $exception) {
      return response()->json(['ok' => false, 'message' => $exception->getMessage()], 409);
    }
  }
}
