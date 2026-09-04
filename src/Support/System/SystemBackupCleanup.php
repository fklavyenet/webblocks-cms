<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Models\SystemUpdateRun;

class SystemBackupCleanup
{
  public function __construct(
    private readonly SystemSettings $settings,
    private readonly SystemBackupManager $backups,
  ) {}

  public function preview(): SystemBackupCleanupResult
  {
    if (! Schema::hasTable('wbcms_system_backups')) {
      return new SystemBackupCleanupResult([], 0);
    }

    $policy = $this->settings->backupCleanupSettings();
    $candidates = collect();

    foreach ([
      SystemBackup::TYPE_PRE_UPDATE => $policy['pre_update_days'],
      SystemBackup::TYPE_RESTORE_SAFETY => $policy['restore_safety_days'],
      SystemBackup::TYPE_CONTENT_APPLY => $policy['content_apply_days'],
    ] as $type => $days) {
      $query = SystemBackup::query()
        ->where('type', $type)
        ->where('status', '!=', SystemBackup::STATUS_RUNNING)
        ->where('created_at', '<', now()->subDays($days))
        ->orderByDesc('created_at')
        ->orderByDesc('id');

      if ($type === SystemBackup::TYPE_PRE_UPDATE) {
        $protectedIds = SystemBackup::query()
          ->where('type', $type)
          ->where('status', SystemBackup::STATUS_COMPLETED)
          ->latest('created_at')
          ->latest('id')
          ->limit($policy['keep_latest_pre_update'])
          ->pluck('id');

        $query->whereNotIn('id', $protectedIds->merge($this->failedUpdateBackupIds())->unique());
      }

      $candidates->push(...$query->get());
    }

    $candidates = $candidates->unique('id')->sortBy('created_at')->values();

    return new SystemBackupCleanupResult(
      $candidates->pluck('id')->map(fn ($id): int => (int) $id)->all(),
      (int) $candidates->sum(fn (SystemBackup $backup): int => (int) ($backup->archive_size_bytes ?? 0)),
    );
  }

  private function failedUpdateBackupIds()
  {
    if (! Schema::hasTable('wbcms_system_update_runs') || ! Schema::hasColumn('wbcms_system_update_runs', 'started_at')) {
      return collect();
    }

    $ids = collect();
    $runs = SystemUpdateRun::query()
      ->whereIn('status', [SystemUpdateRun::STATUS_FAILED, SystemUpdateRun::STATUS_RESTORED])
      ->where('created_at', '>=', now()->subDays(90))
      ->get(['from_version', 'to_version', 'started_at', 'created_at']);

    foreach ($runs as $run) {
      $startedAt = $run->started_at ?? $run->created_at;

      if ($startedAt === null) {
        continue;
      }

      $match = SystemBackup::query()
        ->where('type', SystemBackup::TYPE_PRE_UPDATE)
        ->where('label', 'like', '%'.$run->from_version.' -> '.$run->to_version.'%')
        ->whereBetween('created_at', [$startedAt->copy()->subMinutes(15), $startedAt->copy()->addMinutes(5)])
        ->latest('created_at')
        ->value('id');

      if ($match !== null) {
        $ids->push((int) $match);
      }
    }

    return $ids;
  }

  public function run(bool $force = false): SystemBackupCleanupResult
  {
    $preview = $this->preview();

    if (! $force && ! $this->settings->backupCleanupEnabled()) {
      return $preview;
    }

    if ($preview->candidateIds === []) {
      return $preview;
    }

    $deletedIds = [];
    $deletedBytes = 0;
    $failures = [];

    foreach (SystemBackup::query()->whereIn('id', $preview->candidateIds)->orderBy('id')->get() as $backup) {
      $bytes = (int) ($backup->archive_size_bytes ?? 0);

      try {
        $this->backups->deleteBackupRecord($backup);
        $deletedIds[] = (int) $backup->id;
        $deletedBytes += $bytes;
      } catch (Throwable $throwable) {
        $failures[] = ['id' => (int) $backup->id, 'message' => 'Backup could not be deleted.'];
        Log::warning('Automatic backup cleanup could not delete a backup.', [
          'backup_id' => $backup->id,
          'exception' => $throwable::class,
          'message' => $throwable->getMessage(),
        ]);
      }
    }

    Log::info('Automatic backup cleanup completed.', [
      'candidate_count' => $preview->candidateCount(),
      'deleted_count' => count($deletedIds),
      'deleted_bytes' => $deletedBytes,
      'failure_count' => count($failures),
    ]);

    return new SystemBackupCleanupResult(
      $preview->candidateIds,
      $preview->candidateBytes,
      $deletedIds,
      $deletedBytes,
      $failures,
    );
  }
}
