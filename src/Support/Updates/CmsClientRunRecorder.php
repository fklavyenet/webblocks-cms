<?php

namespace WebBlocks\Cms\Support\Updates;

use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateRunRetention;
use WebBlocks\Cms\Support\Updates\Client\Contracts\RunRecorder;

final class CmsClientRunRecorder implements RunRecorder
{
  public function __construct(private readonly SystemUpdateRunRetention $retention) {}

  public function start(string $fromVersion, string $toVersion, ?int $userId): mixed
  {
    return SystemUpdateRun::query()->create([
      'from_version' => $fromVersion,
      'to_version' => $toVersion,
      'status' => SystemUpdateRun::STATUS_RUNNING,
      'summary' => 'Update started.',
      'started_at' => now(),
      'triggered_by_user_id' => $userId,
    ]);
  }

  public function finish(mixed $ref, string $status, string $summary, string $output, int $warningCount, int $durationMs): void
  {
    if (! $ref instanceof SystemUpdateRun) {
      return;
    }

    $ref->forceFill([
      'status' => $status,
      'summary' => $summary,
      'output' => $output,
      'warning_count' => $warningCount,
      'duration_ms' => $durationMs,
      'finished_at' => now(),
    ])->save();
  }

  public function prune(): void
  {
    $this->retention->prune();
  }

  public function all(): array
  {
    return $this->retention->retainedRuns()->map(fn (SystemUpdateRun $run): array => [
      'id' => $run->id,
      'from_version' => $run->from_version,
      'to_version' => $run->to_version,
      'status' => $run->status,
      'summary' => $run->summary,
      'output' => $run->output,
      'warning_count' => $run->warning_count,
      'duration_ms' => $run->duration_ms,
      'started_at' => $run->started_at?->toIso8601String(),
      'finished_at' => $run->finished_at?->toIso8601String(),
      'user_id' => $run->triggered_by_user_id,
    ])->all();
  }
}
