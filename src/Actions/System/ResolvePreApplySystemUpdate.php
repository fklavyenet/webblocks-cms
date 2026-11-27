<?php

namespace WebBlocks\Cms\Actions\System;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Models\SystemUpdateRequest;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Queries\System\UnfinishedSystemUpdates;
use WebBlocks\Cms\Support\System\BackupOperations;
use WebBlocks\Cms\Support\System\Updates\CmsComposerState;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateLiveness;
use WebBlocks\Cms\Support\System\Updates\UpdateChainSchema;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Support\System\Updates\UpdateInstaller;
use WebBlocks\Cms\Support\WebBlocks;

/** Resolve only proven stopped work that never left its pre-apply backup phase. */
class ResolvePreApplySystemUpdate
{
  public function execute(string $kind, string $id, int $actorId, string $reason): int
  {
    app(CmsPublisherClientConfigurator::class)->configure();
    $queries = app(UnfinishedSystemUpdates::class);
    $locks = [];
    $acquire = function (string $name) use (&$locks): void {
      $lock = Cache::lock($name, 120);
      if (! $lock->get()) {
        throw new UpdateException(__('webblocks-cms::update_recovery.refused'));
      }
      $locks[$name] = $lock;
    };
    try {
      $acquire('system-updates:admission');
      $acquire((string) config('publisher-client.lock.name', 'system-updates:run'));

      return app(BackupOperations::class)->run(function () use ($queries, $kind, $id, $actorId, $reason, $acquire, &$locks): int {
        return DB::transaction(function () use ($queries, $kind, $id, $actorId, $reason, $acquire, &$locks): int {
          $record = $queries->find($kind, $id, true);
          $related = $queries->related($record)->sortBy(fn (Model $row) => $row->getTable().':'.$row->getKey());
          foreach ($related as $row) {
            $row->newQuery()->whereKey($row->getKey())->lockForUpdate()->firstOrFail();
            if ($row instanceof AdminUpdateOperation) {
              if ($row->chain_protocol === 1) {
                throw new UpdateException(__('webblocks-cms::update_recovery.resolve_refused'));
              }
              $acquire('system-updates:worker:'.$row->id);
            }
          }
          $lockedKeys = $related->map(fn (Model $row) => $row->getTable().':'.$row->getKey())->all();
          $related = $queries->related($record->fresh())->sortBy(fn (Model $row) => $row->getTable().':'.$row->getKey());
          if ($lockedKeys !== $related->map(fn (Model $row) => $row->getTable().':'.$row->getKey())->all()) {
            throw new UpdateException(__('webblocks-cms::update_recovery.resolve_refused'));
          }
          $evidence = app(SystemUpdateLiveness::class)->inspect($record->fresh(), array_keys($locks));
          if (! in_array($evidence['state'], ['stopped', 'recovery'], true)
            || ! in_array('worker_dead', $evidence['reasons'], true)
            || array_diff($evidence['reasons'], ['worker_dead', 'recovery_required']) !== []) {
            throw new UpdateException(__('webblocks-cms::update_recovery.resolve_refused'));
          }
          $runs = $related->whereInstanceOf(SystemUpdateRun::class);
          $run = $runs->sole();
          $steps = DB::table('wbcms_system_update_run_steps')->where('system_update_run_id', $run->id)->lockForUpdate()->get();
          if ($run->from_version !== WebBlocks::version()
            || $steps->count() !== 1 || $steps->first()->stage !== 'backup' || $steps->first()->finished_at !== null
            || ! in_array($run->status, ['running', 'interrupted'], true)
            || is_file(storage_path('framework/down')) || (glob(base_path('.publisher-recovery-*')) ?: []) !== []) {
            throw new UpdateException(__('webblocks-cms::update_recovery.resolve_refused'));
          }
          app(CmsComposerState::class)->assertCurrent();
          $output = [];
          app(UpdateInstaller::class)->verifyAppliedVersion($run->from_version, $output);
          app(UpdateChainSchema::class)->assertReady();
          $this->assertOwned($locks);
          $note = __('webblocks-cms::update_recovery.resolved_note', [], 'en');
          foreach ($related as $row) {
            if (! in_array($row->status, ['running', 'interrupted'], true) || ($row->worker_pid ?? null) === null || ! $row->worker_host) {
              throw new UpdateException(__('webblocks-cms::update_recovery.resolve_refused'));
            }
            $type = array_search(get_class($row), UnfinishedSystemUpdates::MODELS, true);
            DB::table('wbcms_update_reconciliations')->insert([
              'record_type' => $type, 'record_id' => (string) $row->getKey(), 'actor_id' => $actorId,
              'previous_status' => $row->status, 'status' => 'resolved_pre_apply', 'reason' => $reason,
              'worker_pid' => $row->worker_pid, 'worker_host' => $row->worker_host,
              'last_heartbeat_at' => $row->heartbeat_at, 'worker_state' => 'dead',
              'admission_lock_verified' => true, 'run_lock_verified' => true, 'recovery_required' => false, 'checked_at' => now(),
            ]);
            $row->forceFill(['status' => 'failed', 'recovery_required' => false]);
            if ($row instanceof SystemUpdateRun) {
              $row->output = trim((string) $row->output).PHP_EOL.(string) $row->summary.PHP_EOL.$note;
              $row->summary = $note;
            } elseif ($row instanceof SystemUpdateRequest) {
              $row->code = 'system_update_interrupted_before_apply';
            } else {
              $row->summary = $note;
            }
            // The exact process death time is unknown: retain original timestamps and duration.
            $row->save();
          }
          DB::table('wbcms_system_update_run_steps')->where('id', $steps->first()->id)->update(['state' => 'interrupted']);
          // Keep the partial archive as evidence. Do not delete or turn it into a successful backup.
          SystemBackup::where('status', 'running')->where('type', 'pre_update')->where('started_at', $run->started_at)
            ->where('label', 'Pre-update backup before '.$run->from_version.' -> '.$run->to_version)
            ->update(['status' => 'failed', 'summary' => $note, 'error_message' => $note]);
          $this->assertOwned($locks);

          return $related->count();
        });
      });
    } finally {
      foreach (array_reverse($locks) as $lock) {
        $lock->release();
      }
    }
  }

  private function assertOwned(array $locks): void
  {
    foreach ($locks as $lock) {
      if (! $lock->isOwnedByCurrentProcess()) {
        throw new UpdateException(__('webblocks-cms::update_recovery.refused'));
      }
    }
  }
}
