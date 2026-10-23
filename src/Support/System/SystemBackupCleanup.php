<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Support\Facades\Log;
use Throwable;
use WebBlocks\Cms\Models\SystemBackup;

class SystemBackupCleanup
{
  public function __construct(
    private readonly SystemSettings $settings,
    private readonly SystemBackupManager $backups,
  ) {}

  public function preview(): SystemBackupCleanupResult
  {
    $plan = app(BackupRetentionPlan::class)->overview();
    $items = array_values(array_filter($plan['items'], fn ($item) => $item['reason'] === 'eligible'));

    return new SystemBackupCleanupResult(array_column($items, 'id'), $plan['storage']['eligible_bytes'], overview: $plan);
  }

  public function run(bool $force = false, string $source = 'manual'): SystemBackupCleanupResult
  {
    if (! $force && ! $this->settings->backupCleanupEnabled()) {
      return $this->preview();
    }
    try {
      return app(BackupOperations::class)->run(function () use ($source): SystemBackupCleanupResult {
        $preview = $this->preview();
        if (! ($preview->overview['ready'] ?? false)) {
          return new SystemBackupCleanupResult([], 0, failures: [['id' => 0, 'message' => 'schema_missing']], overview: $preview->overview);
        }
        $state = app(MaintenanceCleanupState::class);
        $state->start('backups', $source === 'scheduled' ? 'scheduled' : 'manual');
        $deletedIds = [];
        $deletedBytes = 0;
        $failures = [];
        foreach ($preview->candidateIds as $id) {
          // Deletion must never rely on a plan from an earlier request.
          $backup = SystemBackup::find($id);
          if (! $backup) {
            continue;
          }
          $path = null;
          $bytes = 0;
          try {
            $path = $backup->archive_path ? app(SystemBackupArchiveResolver::class)->resolvePath($backup->archive_path)->absolutePath : null;
            $bytes = $path && is_file($path) ? (int) filesize($path) : 0;
            $this->backups->deleteBackupRecord($backup);
            $deletedIds[] = $id;
          } catch (Throwable $throwable) {
            $failures[] = ['id' => $id, 'message' => 'delete_failed'];
            Log::warning('Backup cleanup could not delete an archive.', ['backup_id' => $id, 'exception' => $throwable::class]);
          } finally {
            clearstatcache(true, $path ?? '');
            $deletedBytes += $path && ! is_file($path) ? $bytes : 0;
          }
        }

        $state->finish('backups', new MaintenanceCleanupResult(count($preview->candidateIds), $preview->candidateBytes, count($deletedIds), $deletedBytes, $failures));

        return new SystemBackupCleanupResult($preview->candidateIds, $preview->candidateBytes, $deletedIds, $deletedBytes, $failures, $preview->overview);
      });
    } catch (Throwable $throwable) {
      Log::warning('Backup cleanup could not acquire its operation lease.', ['exception' => $throwable::class]);

      return new SystemBackupCleanupResult([], 0, failures: [['id' => 0, 'message' => 'unavailable']]);
    }
  }
}
