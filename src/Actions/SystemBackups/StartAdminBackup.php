<?php

namespace WebBlocks\Cms\Actions\SystemBackups;

use Illuminate\Support\Facades\Log;
use Throwable;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Support\System\AdminBackupLauncher;
use WebBlocks\Cms\Support\System\SystemBackupManager;

final class StartAdminBackup
{
  public function execute(array $values, int $actorId): SystemBackup
  {
    $retention = app(CreateManagedBackup::class)->retention($values);
    $backup = app(SystemBackupManager::class)->reserveManualBackup($retention, $actorId);

    try {
      app(AdminBackupLauncher::class)->launch($backup->id, $backup->archive_filename);
    } catch (Throwable $exception) {
      Log::warning('Backup worker launch failed.', ['backup_id' => $backup->id, 'exception' => $exception::class]);
      $message = __('webblocks-cms::backup-retention.worker_unavailable');
      SystemBackup::query()->whereKey($backup->id)->where('archive_filename', $backup->archive_filename)
        ->where('status', SystemBackup::STATUS_RUNNING)->whereNull('started_at')
        ->update([
          'status' => SystemBackup::STATUS_FAILED, 'finished_at' => now(),
          'summary' => $message, 'output' => $message, 'error_message' => $message,
        ]);
    }

    return $backup->refresh();
  }
}
