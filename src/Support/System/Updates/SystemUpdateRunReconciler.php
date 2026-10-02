<?php

namespace WebBlocks\Cms\Support\System\Updates;

use WebBlocks\Cms\Models\SystemUpdateRequest;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\WebBlocks;

class SystemUpdateRunReconciler
{
  public function reconcile(?SystemUpdateRun $run = null): ?SystemUpdateRun
  {
    if (! app(SystemUpdateRunRetention::class)->schemaReady()) {
      return null;
    }

    $run ??= SystemUpdateRun::query()->latest()->first();

    if (! $run || $run->status !== SystemUpdateRun::STATUS_FAILED) {
      return null;
    }

    $currentVersion = WebBlocks::version();

    if ((string) $run->to_version !== $currentVersion) {
      return null;
    }

    $output = (string) $run->output;

    if (! str_contains($output, 'Post-update version verified as '.$currentVersion.' from canonical WebBlocks version source.')) {
      return null;
    }

    $marker = 'Post-apply reconciliation: active CMS code still reports '.$currentVersion.'; the previous failure was recorded after the target version had been verified.';
    $lines = trim($output) === '' ? [] : [$output];

    if (! str_contains($output, $marker)) {
      $lines[] = $marker;
    }

    $run->forceFill([
      'status' => SystemUpdateRun::STATUS_SUCCESS_WITH_WARNINGS,
      'summary' => 'Updated to '.$currentVersion.'; a post-apply finalization warning was reconciled.',
      'output' => implode(PHP_EOL, $lines),
      'warning_count' => max(1, (int) $run->warning_count),
      'finished_at' => $run->finished_at ?? now(),
    ])->save();

    return $run;
  }

  public function reconcileReceipt(SystemUpdateRequest $receipt): void
  {
    $run = $receipt->system_update_run_id ? SystemUpdateRun::query()->find($receipt->system_update_run_id) : null;
    if (! $run) {
      return;
    }
    $run = $this->reconcile($run) ?? $run;
    if (! in_array($run->status, [SystemUpdateRun::STATUS_SUCCESS, SystemUpdateRun::STATUS_SUCCESS_WITH_WARNINGS, SystemUpdateRun::STATUS_FAILED, SystemUpdateRun::STATUS_RESTORED], true)) {
      return;
    }
    $receipt->forceFill([
      'status' => $run->status,
      'code' => in_array($run->status, [SystemUpdateRun::STATUS_SUCCESS, SystemUpdateRun::STATUS_SUCCESS_WITH_WARNINGS], true) ? null : $receipt->code,
      'result' => [
        'summary' => app(SystemUpdateRunOutputSanitizer::class)->sanitize($run->summary),
        'output' => app(SystemUpdateRunOutputSanitizer::class)->sanitize($run->output),
        'warning_count' => $run->warning_count,
        'duration_ms' => $run->duration_ms,
      ],
      'finished_at' => $run->finished_at,
    ]);
    if ($receipt->isDirty()) {
      $receipt->save();
    }
  }
}
