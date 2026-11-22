<?php

namespace WebBlocks\Cms\Actions\System;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Queries\System\UnfinishedSystemUpdates;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateAttention;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateDetailRetention;
use WebBlocks\Cms\Support\System\Updates\UpdateException;

class DismissResolvedSystemUpdate
{
  public function execute(string $kind, string $id, int $actorId): void
  {
    DB::transaction(function () use ($kind, $id, $actorId): void {
      $record = app(UnfinishedSystemUpdates::class)->find($kind, $id, true);
      $attention = app(SystemUpdateAttention::class);
      $audit = $attention->resolvedAudit($record, $attention->audits($kind, $id));

      if ($audit === null) {
        throw new UpdateException(__('webblocks-cms::update_recovery.dismiss_refused'));
      }

      if ($audit->status === 'dismissed') {
        return;
      }

      DB::table('wbcms_update_reconciliations')->insert([
        'record_type' => $kind,
        'record_id' => $id,
        'actor_id' => $actorId,
        'previous_status' => $record->status,
        'status' => 'dismissed',
        'reason' => __('webblocks-cms::update_recovery.dismissed'),
        'worker_pid' => $audit->worker_pid,
        'worker_host' => $audit->worker_host,
        'last_heartbeat_at' => $audit->last_heartbeat_at,
        'worker_state' => $audit->worker_state,
        'admission_lock_verified' => $audit->admission_lock_verified,
        'run_lock_verified' => $audit->run_lock_verified,
        'recovery_required' => false,
        'checked_at' => now(),
      ]);
    });

    app(SystemUpdateDetailRetention::class)->pruneAuditHistory();
  }
}
