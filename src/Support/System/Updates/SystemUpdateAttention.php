<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\UpdateChainStep;
use WebBlocks\Cms\Queries\System\UnfinishedSystemUpdates;

class SystemUpdateAttention
{
  public function rows(): Collection
  {
    return app(UnfinishedSystemUpdates::class)->records()->map(function (array $item): array {
      $row = $item['record'];
      $item['audits'] = $this->audits($item['kind'], (string) $row->getKey());
      $item['can_dismiss'] = $this->resolvedAudit($row, $item['audits']) !== null;
      $item['liveness'] = app(SystemUpdateLiveness::class)->inspect($row);
      $item['from_version'] = $row->from_version ?? $row->expected_current_version;
      $item['target_version'] = $row->to_version ?? $row->target_version;
      $item['chain_steps'] = $row instanceof AdminUpdateOperation && $row->chain_protocol === 1 && app(UpdateChainSchema::class)->stepsReady()
        ? UpdateChainStep::where('operation_id', $row->id)->orderBy('position')->get() : collect();

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
