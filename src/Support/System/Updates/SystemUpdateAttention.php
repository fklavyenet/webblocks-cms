<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Models\UpdateChainStep;
use WebBlocks\Cms\Queries\System\UnfinishedSystemUpdates;
use WebBlocks\Cms\Support\WebBlocks;

class SystemUpdateAttention
{
  public function rows(): Collection
  {
    $seen = [];

    return app(UnfinishedSystemUpdates::class)->records()->filter(function (array $item) use (&$seen): bool {
      $related = app(UnfinishedSystemUpdates::class)->related($item['record']);
      $keys = $related->map(fn ($row) => $row->getTable().':'.$row->getKey())->all();
      if (array_intersect($seen, $keys) !== []) {
        return false;
      }
      $seen = array_merge($seen, $keys);

      return true;
    })->values()->map(function (array $item): array {
      $relatedRecords = app(UnfinishedSystemUpdates::class)->related($item['record']);
      $operation = $relatedRecords->whereInstanceOf(AdminUpdateOperation::class)->first();
      if ($operation) {
        $item = ['kind' => 'operation', 'record' => $operation];
      }
      $row = $item['record'];
      $item['audits'] = $this->audits($item['kind'], (string) $row->getKey());
      $item['can_dismiss'] = $this->resolvedAudit($row, $item['audits']) !== null;
      $item['related'] = app(UnfinishedSystemUpdates::class)->related($row)->map(fn ($related) => [
        'kind' => array_search(get_class($related), UnfinishedSystemUpdates::MODELS, true), 'id' => (string) $related->getKey(),
      ]);
      $item['liveness'] = app(SystemUpdateLiveness::class)->inspect($row);
      $item['from_version'] = $row->from_version ?? $row->expected_current_version;
      $item['target_version'] = $row->to_version ?? $row->target_version;
      $item['chain_steps'] = $row instanceof AdminUpdateOperation && $row->chain_protocol === 1 && app(UpdateChainSchema::class)->stepsReady()
        ? UpdateChainStep::where('operation_id', $row->id)->orderBy('position')->get() : collect();

      $run = $relatedRecords->whereInstanceOf(SystemUpdateRun::class)->first();
      $stages = $run && Schema::hasTable('wbcms_system_update_run_steps') ? DB::table('wbcms_system_update_run_steps')->where('system_update_run_id', $run->id)->get() : collect();
      $item['can_resolve_pre_apply'] = $run && $run->from_version === WebBlocks::version()
        && $stages->count() === 1 && $stages->first()->stage === 'backup' && $stages->first()->finished_at === null
        && ! ($operation?->chain_protocol === 1)
        && in_array($item['liveness']['state'], ['stopped', 'recovery'], true)
        && in_array('worker_dead', $item['liveness']['reasons'], true)
        && array_diff($item['liveness']['reasons'], ['worker_dead', 'recovery_required']) === []
        && $item['chain_steps']->isEmpty();
      $needsReview = $row->status === 'running' || (in_array($row->status, ['pending', 'queued'], true)
        && (! $row->created_at || $row->created_at->lt(now()->subSeconds(60))));
      $item['display_status'] = $needsReview && $item['liveness']['state'] !== 'active' ? 'attention' : $row->status;

      return $item;
    });
  }

  public function audits(string $kind, string $id): Collection
  {
    return Schema::hasTable('wbcms_update_reconciliations')
      ? DB::table('wbcms_update_reconciliations')->where('record_type', $kind)->where('record_id', $id)->orderByDesc('id')->get()
      : collect();
  }

  public function resolvedAudit(Model $record, Collection $audits): ?object
  {
    $latest = $audits->first();
    if ($record->status !== 'interrupted' || $record->recovery_required || ! $latest
      || $latest->recovery_required || $latest->worker_state !== 'dead'
      || ! in_array($latest->status, ['interrupted', 'dismissed'], true)) {
      return null;
    }

    $related = app(UnfinishedSystemUpdates::class)->related($record);
    if ($related->contains(fn (Model $row) => $row->recovery_required || in_array($row->status, ['pending', 'queued', 'running'], true))) {
      return null;
    }

    $chains = $related->whereInstanceOf(AdminUpdateOperation::class)->where('chain_protocol', 1);
    if ($chains->isNotEmpty() && ($chains->contains('status', 'interrupted') || ! app(UpdateChainSchema::class)->stepsReady()
      || UpdateChainStep::whereIn('operation_id', $chains->pluck('id'))->whereIn('status', ['queued', 'running', 'interrupted'])->exists())) {
      return null;
    }

    return $latest;
  }
}
