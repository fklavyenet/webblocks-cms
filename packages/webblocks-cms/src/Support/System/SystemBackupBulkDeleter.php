<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Support\Facades\Log;
use Throwable;
use WebBlocks\Cms\Models\SystemBackup;

class SystemBackupBulkDeleter
{
  public function __construct(
    private readonly SystemBackupManager $systemBackupManager,
  ) {}

  public function deleteSelected(array $ids): SystemBackupBulkDeleteResult
  {
    $ids = collect($ids)
      ->map(fn ($id): int => (int) $id)
      ->filter(fn (int $id): bool => $id > 0)
      ->unique()
      ->values();

    $backups = SystemBackup::query()
      ->whereIn('id', $ids)
      ->get()
      ->keyBy('id');

    $deletedIds = [];
    $failed = [];

    foreach ($ids as $id) {
      $backup = $backups->get($id);

      if (! $backup instanceof SystemBackup) {
        $failed[] = [
          'id' => $id,
          'message' => 'The backup no longer exists.',
        ];

        continue;
      }

      try {
        $this->systemBackupManager->deleteBackupRecord($backup);
        $deletedIds[] = $id;
      } catch (Throwable $throwable) {
        Log::warning('Selected backup could not be deleted during bulk delete.', [
          'backup_id' => $backup->id,
          'backup_status' => $backup->status,
          'backup_type' => $backup->type,
          'exception' => $throwable::class,
          'message' => $throwable->getMessage(),
        ]);

        $failed[] = [
          'id' => $id,
          'message' => $this->safeFailureMessage($throwable),
        ];
      }
    }

    return new SystemBackupBulkDeleteResult($ids->count(), $deletedIds, $failed);
  }

  private function safeFailureMessage(Throwable $throwable): string
  {
    return match ($throwable->getMessage()) {
      'Running backup cannot be deleted unless you explicitly confirm it is stuck.',
      'Backup archive path is invalid.',
      'Backup archive file could not be deleted.' => $throwable->getMessage(),
      default => 'Review the logs for details.',
    };
  }
}
