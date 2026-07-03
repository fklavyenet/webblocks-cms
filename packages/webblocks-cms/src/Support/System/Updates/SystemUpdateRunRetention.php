<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\SystemUpdateRun;

class SystemUpdateRunRetention
{
  public function retainedRuns(?int $keep = null): Collection
  {
    if (! Schema::hasTable('wbcms_system_update_runs')) {
      return collect();
    }

    return SystemUpdateRun::query()
      ->with('triggeredBy')
      ->orderByDesc('started_at')
      ->orderByDesc('id')
      ->limit($this->keep($keep))
      ->get();
  }

  public function prune(?int $keep = null): int
  {
    if (! Schema::hasTable('wbcms_system_update_runs')) {
      return 0;
    }

    $keep = $this->keep($keep);
    $protectedIds = $this->protectedIds($keep);
    $deleted = 0;

    SystemUpdateRun::query()
      ->whereNotIn('id', $protectedIds)
      ->orderBy('started_at')
      ->orderBy('id')
      ->chunkById(100, function (Collection $runs) use (&$deleted): void {
        foreach ($runs as $run) {
          $deleted += $run->delete() ? 1 : 0;
        }
      });

    return $deleted;
  }

  private function protectedIds(int $keep): array
  {
    $ids = SystemUpdateRun::query()
      ->orderByDesc('started_at')
      ->orderByDesc('id')
      ->limit($keep)
      ->pluck('id')
      ->all();

    $inProgressIds = SystemUpdateRun::query()
      ->whereIn('status', [SystemUpdateRun::STATUS_PENDING, SystemUpdateRun::STATUS_RUNNING])
      ->pluck('id')
      ->all();

    $latestFailedRun = SystemUpdateRun::query()
      ->where('status', SystemUpdateRun::STATUS_FAILED)
      ->orderByDesc('started_at')
      ->orderByDesc('id')
      ->first();

    if ($latestFailedRun && ! $this->latestSuccessIsNewerThan($latestFailedRun)) {
      $ids[] = $latestFailedRun->id;
    }

    return array_values(array_unique(array_merge($ids, $inProgressIds)));
  }

  private function latestSuccessIsNewerThan(SystemUpdateRun $run): bool
  {
    $latestSuccess = SystemUpdateRun::query()
      ->whereIn('status', [SystemUpdateRun::STATUS_SUCCESS, SystemUpdateRun::STATUS_SUCCESS_WITH_WARNINGS])
      ->orderByDesc('started_at')
      ->orderByDesc('id')
      ->first();

    if (! $latestSuccess) {
      return false;
    }

    $successStartedAt = $latestSuccess->started_at?->getTimestamp() ?? 0;
    $runStartedAt = $run->started_at?->getTimestamp() ?? 0;

    if ($successStartedAt !== $runStartedAt) {
      return $successStartedAt > $runStartedAt;
    }

    return $latestSuccess->id > $run->id;
  }

  private function keep(?int $keep): int
  {
    $configured = $keep ?? (int) config('webblocks-updates.runs.keep', 5);

    return max(1, min(50, $configured));
  }
}
