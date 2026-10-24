<?php

namespace WebBlocks\Cms\Support\System;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Throwable;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Models\SystemUpdateRun;

/** One read-only decision engine for the panel, API, CLI and scheduled cleanup. */
final class BackupRetentionPlan
{
  public function __construct(
    private readonly SystemSettings $settings,
    private readonly SystemBackupArchiveResolver $resolver,
    private readonly BackupRestoreArchiveInspector $inspector,
  ) {}

  public function overview(bool $verifyArchives = true): array
  {
    $policy = $this->settings->backupCleanupSettings();
    $state = app(MaintenanceCleanupState::class)->overview();
    $ready = BackupRetentionSchema::ready() && $state['ready'];
    $rows = Schema::hasTable('wbcms_system_backups') ? SystemBackup::query()->latest('created_at')->latest('id')->get($ready ? ['id', 'type', 'status', 'archive_path', 'archive_filename', 'is_protected', 'retention_mode', 'expires_at', 'created_at', 'finished_at'] : ['*']) : collect();
    $limits = ['pre_update' => $policy['keep_latest_pre_update'], 'restore_safety' => $policy['keep_latest_restore_safety'], 'content_apply' => $policy['keep_latest_content_apply'], 'manual' => $policy['keep_latest_manual']];
    $validation = app(BackupArchiveValidation::class);
    $records = $verifyArchives ? collect() : $validation->records($rows->modelKeys());
    $pending = [];
    $floors = [];
    $available = [];
    $sizes = [];
    $paths = [];
    $physicalArchives = [];
    $floorPaths = [];
    foreach ($rows as $backup) {
      try {
        $resolution = $this->resolver->resolveForRetention($backup);
        $available[$backup->id] = $resolution->isAvailable();
        $paths[$backup->id] = $resolution->absolutePath;
        $path = $resolution->absolutePath;
        $size = $path !== null && is_file($path) ? filesize($path) : false;
        if ($size !== false) {
          $physicalArchives[$backup->id] = ['path' => $path, 'bytes' => $size];
        }
        $sizes[$backup->id] = $resolution->isAvailable() && $size !== false ? $size : 0;
        if ($backup->isSuccessful() && $resolution->isAvailable() && ($limits[$backup->type] ?? 0) > 0 && ! isset($floorPaths[$backup->type][$resolution->absolutePath])) {
          if ($verifyArchives) {
            $this->inspector->inspect($resolution->absolutePath);
          } else {
            $status = $validation->status($records->get($backup->id), $resolution->absolutePath);
            if ($status !== 'valid') {
              if ($status === 'pending') {
                $pending[$backup->id] = true;
              }

              continue;
            }
          }
          $floors[$backup->id] = true;
          $floorPaths[$backup->type][$resolution->absolutePath] = true;
          $limits[$backup->type]--;
        }
      } catch (Throwable) {
        // Invalid archives never occupy a successful recovery floor.
      }
    }
    $failedUpdateIds = $this->failedUpdateBackupIds();
    $items = [];
    foreach ($rows as $backup) {
      $mode = $backup->retention_mode ?? 'policy';
      $expires = null;
      $reason = null;
      if (! $ready) {
        $reason = 'schema_missing';
      } elseif ($backup->is_protected) {
        $reason = 'protected';
      } elseif ($backup->isRunning()) {
        $reason = 'running';
      } elseif ($failedUpdateIds->contains($backup->id)) {
        $reason = 'failed_update';
      } elseif (isset($floors[$backup->id])) {
        $reason = 'minimum_count';
      } elseif (! ($available[$backup->id] ?? false) && ! $backup->isFailed()) {
        $reason = 'unavailable';
      } elseif ($mode === 'never' || ($mode === 'policy' && $backup->type === SystemBackup::TYPE_UPLOADED)) {
        $reason = 'never';
      } elseif ($mode === 'policy' && $backup->type === SystemBackup::TYPE_MANUAL && ! $policy['manual_enabled']) {
        $reason = 'manual_disabled';
      } else {
        $days = match ($backup->type) {
          'pre_update' => $policy['pre_update_days'],
          'restore_safety' => $policy['restore_safety_days'],
          'content_apply' => $policy['content_apply_days'],
          'manual' => $policy['manual_days'],
          default => null,
        };
        $expires = $mode === 'expires' ? $backup->expires_at?->toIso8601String() : ($mode === 'policy' && $days !== null ? CarbonImmutable::parse($backup->created_at)->addDays($days)->toIso8601String() : null);
        $reason = $expires === null ? 'invalid_policy' : (CarbonImmutable::parse($expires)->lt(now()) ? 'eligible' : 'retained');
      }
      if ($pending !== [] && $reason === 'eligible') {
        $reason = 'validation_pending';
      }
      $items[] = ['id' => (int) $backup->id, 'reason' => $reason, 'expires_at' => $expires, 'bytes' => $sizes[$backup->id] ?? 0];
    }
    $retainedPaths = [];
    foreach ($items as $item) {
      if ($item['reason'] !== 'eligible' && ($paths[$item['id']] ?? null)) {
        $retainedPaths[$paths[$item['id']]] = true;
      }
    }
    foreach ($items as &$item) {
      if ($item['reason'] === 'eligible' && isset($retainedPaths[$paths[$item['id']] ?? ''])) {
        $item['reason'] = 'shared_archive';
      }
    }
    unset($item);
    $physicalSum = static function (array $items, string $reason) use ($paths): int {
      $files = [];
      foreach ($items as $item) {
        if ($item['reason'] === $reason && ($paths[$item['id']] ?? null)) {
          $files[$paths[$item['id']]] = $item['bytes'];
        }
      }

      return array_sum($files);
    };
    // Oldest first, including ties. The same plan is re-evaluated under the lease.
    $items = array_reverse($items);
    $measurement = app(StorageUsageReport::class)->backups();
    $eligible = $physicalSum($items, 'eligible');
    $protected = $physicalSum($items, 'protected');
    $budget = (int) round($policy['budget_gb'] * 1073741824);
    $remaining = max(0, $measurement['bytes'] - $eligible);

    return ['ready' => $ready, 'validation_pending' => count($pending), 'last_run' => $state['categories']['backups'] ?? null, 'items' => $items,
      'reason_summary' => $this->reasonSummary($items, $physicalArchives, $measurement), 'storage' => [
      'status' => $measurement['status'], 'total_bytes' => $measurement['bytes'], 'protected_bytes' => $protected,
      'eligible_bytes' => $eligible, 'after_cleanup_bytes' => $remaining, 'budget_bytes' => $budget ?: null,
      'over_budget_bytes' => $budget > 0 ? max(0, $measurement['bytes'] - $budget) : 0,
      'remaining_over_budget_bytes' => $budget > 0 ? max(0, $remaining - $budget) : 0,
      'budget_status' => $budget === 0 ? 'unset' : (in_array($measurement['status'], ['available', 'missing'], true) ? ($measurement['bytes'] >= $budget ? 'exceeded' : ($measurement['bytes'] >= $budget * 0.8 ? 'warning' : 'within')) : 'unavailable'),
    ]];
  }

  private function reasonSummary(array $items, array $archives, array $measurement): array
  {
    // Attribute shared physical files to one determining reason, strongest first.
    $groups = [];
    foreach (['schema_missing', 'protected', 'running', 'failed_update', 'minimum_count', 'never', 'manual_disabled', 'retained', 'invalid_policy', 'unavailable', 'shared_archive', 'validation_pending', 'eligible'] as $reason) {
      $groups[$reason] = ['reason' => $reason, 'record_count' => 0, 'file_count' => 0, 'bytes' => 0];
    }
    $byReason = [];
    foreach ($items as $item) {
      $groups[$item['reason']]['record_count']++;
      if (isset($archives[$item['id']])) {
        $byReason[$item['reason']][] = $archives[$item['id']];
      }
    }
    $seen = [];
    foreach ($groups as $reason => &$group) {
      foreach ($byReason[$reason] ?? [] as $archive) {
        if (! isset($seen[$archive['path']])) {
          $seen[$archive['path']] = true;
          $group['file_count']++;
          $group['bytes'] += $archive['bytes'];
        }
      }
    }
    unset($group);
    $knownBytes = array_sum(array_column($groups, 'bytes'));
    $complete = in_array($measurement['status'], ['available', 'missing'], true)
      && $measurement['files'] >= count($seen) && $measurement['bytes'] >= $knownBytes;

    return [
      'status' => $complete ? $measurement['status'] : ($measurement['status'] === 'unavailable' ? 'unavailable' : 'partial'),
      'groups' => array_values(array_filter($groups, fn ($group) => $group['record_count'] > 0)),
      'record_count' => count($items),
      'file_count' => $complete ? $measurement['files'] : null,
      'bytes' => $complete ? $measurement['bytes'] : null,
      'other_files' => ['file_count' => $complete ? $measurement['files'] - count($seen) : null, 'bytes' => $complete ? $measurement['bytes'] - $knownBytes : null],
    ];
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
}
