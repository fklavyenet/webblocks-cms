<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Queries\System\UnfinishedSystemUpdates;
use WebBlocks\Cms\Support\Updates\Client\Updates\RunHeartbeat;

class SystemUpdateLiveness
{
  public function inspect(Model $record, array $ownedLocks = []): array
  {
    $related = app(UnfinishedSystemUpdates::class)->related($record);
    $reasons = [];
    $active = false;
    $liveWorker = false;
    $unknown = false;
    foreach (UnfinishedSystemUpdates::MODELS as $class) {
      if (! app(UpdateWorkerEvidence::class)->available((new $class)->getTable())) {
        $unknown = true;
        $reasons[] = 'worker_unknown';
      }
    }
    if (! Schema::hasTable('wbcms_update_reconciliations')) {
      $unknown = true;
    }
    $recovery = $related->contains(fn ($row) => $row->recovery_required || ($row instanceof AdminUpdateOperation && $row->chain_protocol === 1));
    $unfinished = $related->filter(fn ($row) => in_array($row->status, ['pending', 'queued', 'running', 'interrupted'], true));
    foreach ($unfinished as $row) {
      $state = app(UpdateWorkerProcess::class)->state($row->worker_pid ? (int) $row->worker_pid : null, $row->worker_host);
      $fresh = $row->heartbeat_at && now()->parse($row->heartbeat_at)->gt(now()->subSeconds(app(RunHeartbeat::class)->staleAfterSeconds()));
      $active = $active || $state === 'alive' || $fresh;
      $liveWorker = $liveWorker || $state === 'alive';
      $unknown = $unknown || $state === 'unknown' || ($fresh && $state === 'dead');
      if ($state === 'unknown') {
        $reasons[] = 'worker_unknown';
      }
      if ($state === 'alive') {
        $reasons[] = 'worker_alive';
      }
      if ($state === 'dead') {
        $reasons[] = 'worker_dead';
      }
      if ($fresh) {
        $reasons[] = 'heartbeat_fresh';
      }
    }
    $relatedKeys = $related->map(fn ($row) => $row->getTable().':'.$row->getKey())->all();
    foreach (app(UnfinishedSystemUpdates::class)->records() as $item) {
      $other = $item['record'];
      if (in_array($other->getTable().':'.$other->getKey(), $relatedKeys, true)
        || ! in_array($other->status, ['pending', 'queued', 'running'], true)) {
        continue;
      }
      $process = app(UpdateWorkerProcess::class)->state($other->worker_pid ? (int) $other->worker_pid : null, $other->worker_host);
      $fresh = $other->heartbeat_at && now()->parse($other->heartbeat_at)->gt(now()->subSeconds(app(RunHeartbeat::class)->staleAfterSeconds()));
      if ($process !== 'dead' || $fresh) {
        $active = $active || $process === 'alive' || $fresh;
        $liveWorker = $liveWorker || $process === 'alive';
        $unknown = true;
        $reasons[] = 'other_work';
      }
    }
    if (! app(RunHeartbeat::class)->isStale()) {
      $active = true;
      $reasons[] = 'heartbeat_fresh';
    }
    $locks = ['system-updates:admission', (string) config('publisher-client.lock.name', 'system-updates:run')];
    foreach ($related->whereInstanceOf(AdminUpdateOperation::class) as $operation) {
      $locks[] = 'system-updates:worker:'.$operation->id;
    }
    foreach (array_unique($locks) as $name) {
      if (! in_array($name, $ownedLocks, true) && ! $this->available($name)) {
        $unknown = true;
        $reasons[] = 'lock_held';
      }
    }
    if ($recovery) {
      $reasons[] = 'recovery_required';
    }
    $state = $liveWorker ? 'active' : ($recovery ? 'recovery' : ($unknown || $active || $unfinished->isEmpty() ? 'unknown' : 'stopped'));

    return ['state' => $state, 'can_reconcile' => $state === 'stopped' && in_array($record->status, ['pending', 'queued', 'running'], true), 'reasons' => array_values(array_unique($reasons))];
  }

  private function available(string $name): bool
  {
    try {
      $lock = Cache::lock($name, 5);
      if (! $lock->get()) {
        return false;
      }
      $lock->release();

      return true;
    } catch (\Throwable) {
      return false;
    }
  }
}
