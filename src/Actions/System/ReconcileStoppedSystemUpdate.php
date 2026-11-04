<?php

namespace WebBlocks\Cms\Actions\System;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Queries\System\UnfinishedSystemUpdates;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateLiveness;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Support\System\Updates\UpdateWorkerEvidence;

class ReconcileStoppedSystemUpdate
{
  public function execute(string $kind, string $id, int $actorId, string $reason): int
  {
    app(CmsPublisherClientConfigurator::class)->configure();
    $queries = app(UnfinishedSystemUpdates::class);
    $locks = [];
    $acquire = function (string $name) use (&$locks): void {
      $lock = Cache::lock($name, 30);
      if (! $lock->get()) {
        throw new UpdateException(__('webblocks-cms::update_recovery.refused'));
      }
      $locks[$name] = $lock;
    };
    $assertOwned = function () use (&$locks): void {
      foreach ($locks as $lock) {
        if (! $lock->isOwnedByCurrentProcess()) {
          throw new UpdateException(__('webblocks-cms::update_recovery.refused'));
        }
      }
    };
    try {
      $acquire('system-updates:admission');
      $acquire((string) config('publisher-client.lock.name', 'system-updates:run'));

      return DB::transaction(function () use ($queries, $kind, $id, $actorId, $reason, $acquire, $assertOwned, &$locks): int {
        $record = $queries->find($kind, $id, true);
        $related = $queries->related($record)->sortBy(fn (Model $row) => $row->getTable().':'.$row->getKey());
        foreach ($related as $row) {
          $row->newQuery()->whereKey($row->getKey())->lockForUpdate()->firstOrFail();
          if ($row instanceof AdminUpdateOperation) {
            $acquire('system-updates:worker:'.$row->id);
          }
        }
        if (! Schema::hasTable('wbcms_update_reconciliations')
          || ! app(SystemUpdateLiveness::class)->inspect($record->fresh(), array_keys($locks))['can_reconcile']) {
          throw new UpdateException(__('webblocks-cms::update_recovery.refused'));
        }
        $changed = 0;
        foreach ($queries->related($record->fresh()) as $row) {
          $assertOwned();
          if (! in_array($row->status, ['pending', 'queued', 'running'], true)) {
            continue;
          }
          if (! app(UpdateWorkerEvidence::class)->available($row->getTable())) {
            throw new UpdateException(__('webblocks-cms::update_recovery.refused'));
          }
          $type = array_search(get_class($row), UnfinishedSystemUpdates::MODELS, true);
          DB::table('wbcms_update_reconciliations')->insert([
            'record_type' => $type, 'record_id' => (string) $row->getKey(), 'actor_id' => $actorId,
            'previous_status' => $row->status, 'status' => 'interrupted', 'reason' => $reason,
            'worker_pid' => $row->worker_pid, 'worker_host' => $row->worker_host,
            'last_heartbeat_at' => $row->heartbeat_at, 'recovery_required' => true, 'checked_at' => now(),
            'worker_state' => 'dead', 'admission_lock_verified' => true, 'run_lock_verified' => true,
          ]);
          // Death proves interruption, not a healthy checkpoint. Preserve original output and timings.
          $row->forceFill(['status' => 'interrupted', 'recovery_required' => true])->save();
          $changed++;
        }
        if ($changed === 0) {
          throw new UpdateException(__('webblocks-cms::update_recovery.refused'));
        }
        $assertOwned();

        return $changed;
      });
    } finally {
      foreach (array_reverse($locks) as $lock) {
        $lock->release();
      }
    }
  }
}
