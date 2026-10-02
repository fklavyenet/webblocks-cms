<?php

namespace WebBlocks\Cms\Support\System\Updates;

use WebBlocks\Cms\Models\SystemUpdateRequest;
use WebBlocks\Cms\Support\Updates\Client\Updates\RunHeartbeat;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;

class SystemUpdateApiPresenter
{
  public function operation(SystemUpdateRequest $receipt): array
  {
    app(SystemUpdateRunReconciler::class)->reconcileReceipt($receipt);
    $status = $receipt->status;
    $result = $receipt->result;
    $attention = $status === 'running'
      && $receipt->created_at->diffInSeconds(now()) >= app(RunHeartbeat::class)->staleAfterSeconds()
      && (! app(SystemUpdater::class)->isLocked() || app(RunHeartbeat::class)->isStale());

    foreach (['summary', 'output'] as $field) {
      if (isset($result[$field])) {
        $result[$field] = app(SystemUpdateRunOutputSanitizer::class)->sanitize($result[$field]);
      }
    }

    return [
      'id' => $receipt->id,
      'run_id' => $receipt->system_update_run_id,
      'status' => $status,
      'requires_attention' => $attention,
      'code' => $receipt->code,
      'expected_current_version' => $receipt->expected_current_version,
      'target_version' => $receipt->target_version,
      'checksum_sha256' => $receipt->checksum_sha256,
      'result' => $result,
      'started_at' => $receipt->created_at->toIso8601String(),
      'finished_at' => $receipt->finished_at?->toIso8601String(),
    ];
  }
}
