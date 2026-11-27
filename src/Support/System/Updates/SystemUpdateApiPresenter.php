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
    $age = $receipt->created_at?->diffInSeconds(now()) ?? PHP_INT_MAX;
    $result = $receipt->result;
    $attention = (bool) $receipt->recovery_required || ($status === 'running'
      && $age >= app(RunHeartbeat::class)->staleAfterSeconds()
      && (! app(SystemUpdater::class)->isLocked() || app(RunHeartbeat::class)->isStale()));

    $progress = $receipt->admin_update_operation_id
      ? app(CmsUpdateProgress::class)->payload($receipt->admin_update_operation_id)
      : ($receipt->system_update_run_id ? app(CmsUpdateProgress::class)->payload(runId: $receipt->system_update_run_id) : null);
    $liveness = in_array($status, ['queued', 'running', 'interrupted'], true)
      ? app(SystemUpdateLiveness::class)->inspect($receipt) : null;
    if (in_array($liveness['state'] ?? '', ['stopped', 'recovery'], true)
      || in_array('worker_dead', $liveness['reasons'] ?? [], true)
      || ($status === 'queued' && $age >= 60)) {
      $attention = true;
    }
    if (($liveness['state'] ?? null) === 'active' && ! $receipt->recovery_required) {
      $attention = false;
    }
    if ($progress && $attention) {
      $progress['running'] = false;
      $progress['state'] = 'interrupted';
    }

    foreach (['summary', 'output'] as $field) {
      if (isset($result[$field])) {
        $result[$field] = app(SystemUpdateRunOutputSanitizer::class)->sanitize($result[$field]);
      }
    }

    return [
      'id' => $receipt->id,
      'execution' => $receipt->admin_update_operation_id ? 'background' : 'synchronous',
      'worker_operation_id' => $receipt->admin_update_operation_id,
      'progress' => $progress,
      'worker_state' => $liveness['state'] ?? null,
      'attention_reasons' => $liveness['reasons'] ?? [],
      'run_id' => $receipt->system_update_run_id,
      'status' => $status,
      'requires_attention' => $attention,
      'recovery_required' => (bool) $receipt->recovery_required,
      'code' => $receipt->code,
      'expected_current_version' => $receipt->expected_current_version,
      'target_version' => $receipt->target_version,
      'checksum_sha256' => $receipt->checksum_sha256,
      'result' => $result,
      'started_at' => $receipt->created_at?->toIso8601String(),
      'finished_at' => $receipt->finished_at?->toIso8601String(),
    ];
  }
}
