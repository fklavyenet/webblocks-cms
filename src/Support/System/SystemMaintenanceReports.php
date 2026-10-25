<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use WebBlocks\Cms\Queries\SystemHealthQuery;
use WebBlocks\Cms\Support\WebBlocks;

/** Transient, timestamped reporting cache. It never authorizes deletion or restore. */
class SystemMaintenanceReports
{
  public const FRESH_SECONDS = 300;

  public const RETAIN_SECONDS = 604800;

  public function cacheKey(): string
  {
    return 'webblocks-cms:maintenance-report:'.WebBlocks::VERSION.':'.hash('sha256', base_path().config('database.default').config('database.connections.'.config('database.default').'.database'));
  }

  public function read(): array
  {
    try {
      $report = app(MaintenanceReportSnapshot::class)->decode(Cache::get($this->cacheKey()));
      if ($report !== null) {
        $report['status'] = $report['generation'] === $this->generation()
          && now()->timestamp - $report['checked_at'] < self::FRESH_SECONDS ? 'fresh' : 'stale';

        return $report;
      }
    } catch (Throwable) {
      // Missing reporting infrastructure must not make ordinary panel requests expensive.
    }

    return ['status' => 'missing', 'checked_at' => null, 'backup' => null, 'cleanup' => null];
  }

  public function generation(): string
  {
    return (string) Cache::get($this->cacheKey().':generation', '');
  }

  public function invalidate(): void
  {
    try {
      Cache::put($this->cacheKey().':generation', (string) Str::uuid(), self::RETAIN_SECONDS);
    } catch (Throwable) {
      // Reports always expire by age too. Mutations do not depend on this optional cache.
    }
  }

  public function refresh(int $validationLimit = 8): bool
  {
    $lock = Cache::lock($this->cacheKey().':refresh', 900);
    if (! $lock->get()) {
      return false;
    }
    try {
      $snapshot = app(MaintenanceReportSnapshot::class);
      $previous = Cache::get($this->cacheKey());
      app(BackupArchiveValidation::class)->validatePending($validationLimit);
      app(SystemBackupManager::class)->markStaleBackupsAsFailed();
      $generation = $this->generation();
      $report = [
        'snapshot_id' => (string) Str::uuid(),
        'generation' => $generation, 'checked_at' => now()->timestamp,
        'backup' => app(SystemBackupCleanup::class)->preview(verifyArchives: false),
        'cleanup' => app(MaintenanceCleanup::class)->overview(),
      ];
      $payload = $snapshot->encode($report);
      try {
        if (! Cache::put($this->cacheKey(), $payload, self::RETAIN_SECONDS)
          || Cache::get($this->cacheKey()) !== $payload) {
          throw new RuntimeException('Maintenance report could not be read back from cache.');
        }
      } catch (Throwable $exception) {
        // Best effort: retain the prior completed measurement if a store accepts
        // a write but loses, corrupts or cannot read back its value.
        try {
          if ($snapshot->decode($previous) !== null) {
            Cache::put($this->cacheKey(), $previous, max(1, $previous['checked_at'] + self::RETAIN_SECONDS - now()->timestamp));
          } else {
            Cache::forget($this->cacheKey());
          }
        } catch (Throwable) {
          // The original cache failure is the reason refresh did not complete.
        }
        throw $exception;
      }
      app(SystemHealthQuery::class)->refreshSnapshot();

      return true;
    } finally {
      $lock->release();
    }
  }
}
