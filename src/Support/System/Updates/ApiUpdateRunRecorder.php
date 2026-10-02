<?php

namespace WebBlocks\Cms\Support\System\Updates;

use WebBlocks\Cms\Models\SystemUpdateRequest;
use WebBlocks\Cms\Support\Updates\Client\Contracts\RunRecorder;

class ApiUpdateRunRecorder implements RunRecorder
{
  public function __construct(private readonly RunRecorder $delegate, private readonly SystemUpdateRequest $request) {}

  public function start(string $fromVersion, string $toVersion, ?int $userId): mixed
  {
    $run = $this->delegate->start($fromVersion, $toVersion, $userId);
    $this->request->forceFill(['system_update_run_id' => $run->getKey()])->save();

    return $run;
  }

  public function finish(mixed $ref, string $status, string $summary, string $output, int $warningCount, int $durationMs): void
  {
    $this->delegate->finish($ref, $status, $summary, $output, $warningCount, $durationMs);
    $this->request->forceFill([
      'status' => $status,
      'result' => [
        'summary' => app(SystemUpdateRunOutputSanitizer::class)->sanitize($summary),
        'output' => app(SystemUpdateRunOutputSanitizer::class)->sanitize($output),
        'warning_count' => $warningCount,
        'duration_ms' => $durationMs,
      ],
      'finished_at' => now(),
    ])->save();
  }

  public function prune(): void
  {
    $this->delegate->prune();
  }

  public function all(): array
  {
    return $this->delegate->all();
  }
}
