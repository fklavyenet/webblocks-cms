<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Collection;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\System\SystemUpdateInspector;

class SystemUpdateSupportReport
{
  public function __construct(
    private readonly SystemUpdateInspector $inspector,
    private readonly SystemUpdateRunRetention $retention,
    private readonly SystemUpdateRunOutputSanitizer $sanitizer,
  ) {}

  public function build(): array
  {
    $report = $this->inspector->report();
    $runs = $this->retention->retainedRuns();
    $lastRun = $runs->first();

    return [
      'generated_at' => now()->toIso8601String(),
      'product' => $report['environment']['product'] ?? 'webblocks-cms',
      'channel' => $report['environment']['channel'] ?? 'stable',
      'current_version' => $report['installed_version'] ?? null,
      'stored_installed_version' => $report['stored_installed_version'] ?? null,
      'latest_version' => $report['version']['latest_version'] ?? null,
      'update_state' => $report['version']['state'] ?? 'unknown',
      'update_readiness' => $this->readiness($report),
      'last_run' => $lastRun ? $this->runSummary($lastRun, true) : null,
      'retained_runs' => $runs->map(fn (SystemUpdateRun $run): array => $this->runSummary($run))->values()->all(),
    ];
  }

  private function readiness(array $report): array
  {
    return [
      'allowed' => (bool) ($report['auto_update']['allowed'] ?? false),
      'busy' => (bool) ($report['auto_update']['busy'] ?? false),
      'blockers' => collect($report['auto_update']['blockers'] ?? [])
        ->map(fn ($message): string => $this->sanitizer->sanitize((string) $message, 500))
        ->filter()
        ->values()
        ->all(),
      'checks' => collect($report['diagnostics'] ?? [])
        ->map(fn (array $item): array => [
          'label' => $item['label'] ?? 'Check',
          'status' => $item['status'] ?? 'unknown',
          'message' => $this->sanitizer->sanitize((string) ($item['message'] ?? ''), 500),
        ])
        ->values()
        ->all(),
    ];
  }

  private function runSummary(SystemUpdateRun $run, bool $includeDetails = false): array
  {
    $summary = [
      'id' => $run->id,
      'from_version' => $run->from_version,
      'to_version' => $run->to_version,
      'status' => $run->status,
      'summary' => $this->sanitizer->sanitize($run->summary, 1000),
      'warning_count' => $run->warning_count,
      'started_at' => $run->started_at?->toIso8601String(),
      'finished_at' => $run->finished_at?->toIso8601String(),
      'duration_ms' => $run->duration_ms,
    ];

    if ($includeDetails) {
      $summary['output'] = $this->sanitizer->sanitize($run->output);
    }

    return $summary;
  }
}
