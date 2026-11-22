<?php

namespace WebBlocks\Cms\Queries\System;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\SystemUpdateRequest;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Models\UpdateChainStep;

class UnfinishedSystemUpdates
{
  public const MODELS = ['run' => SystemUpdateRun::class, 'operation' => AdminUpdateOperation::class, 'request' => SystemUpdateRequest::class];

  public function query(string $kind): Builder
  {
    $class = self::MODELS[$kind];
    $model = new $class;

    return $model->newQuery()->where(function (Builder $query) use ($kind, $model): void {
      $query->whereIn('status', ['pending', 'queued', 'running'])
        ->orWhere(fn (Builder $interrupted) => $this->visibleInterrupted($interrupted, $kind, $model));
      if (Schema::hasColumn($model->getTable(), 'recovery_required')) {
        $query->orWhere('recovery_required', true);
      }
      if ($model instanceof AdminUpdateOperation && Schema::hasColumns('wbcms_update_chain_steps', ['operation_id', 'status'])) {
        $query->orWhereIn('id', UpdateChainStep::whereIn('status', ['running', 'interrupted'])->select('operation_id'));
      }
    });
  }

  private function visibleInterrupted(Builder $query, string $kind, Model $model): void
  {
    $query->where('status', 'interrupted');
    if (! Schema::hasTable('wbcms_update_reconciliations')) {
      return;
    }

    if ($model instanceof AdminUpdateOperation && Schema::hasColumn($model->getTable(), 'chain_protocol')) {
      // Interrupted chains remain unresolved at admission and stay in review.
      $query->where(function (Builder $operation) use ($kind, $model): void {
        $operation->where('chain_protocol', 1)
          ->orWhere(fn (Builder $nonChain) => $this->withoutDismissal($nonChain, $kind, $model));
      });

      return;
    }

    $this->withoutDismissal($query, $kind, $model);
  }

  private function withoutDismissal(Builder $query, string $kind, Model $model): void
  {
    $query->whereNotExists(function (QueryBuilder $audit) use ($kind, $model): void {
      $audit->selectRaw('1')->from('wbcms_update_reconciliations as dismissal')
        ->where('dismissal.record_type', $kind)
        ->whereColumn('dismissal.record_id', $model->getQualifiedKeyName())
        ->where('dismissal.status', 'dismissed')
        ->where('dismissal.recovery_required', false)
        ->where('dismissal.id', function (QueryBuilder $latest): void {
          $latest->selectRaw('max(id)')->from('wbcms_update_reconciliations')
            ->whereColumn('record_type', 'dismissal.record_type')
            ->whereColumn('record_id', 'dismissal.record_id');
        });
    });
  }

  public function records(): Collection
  {
    return collect(self::MODELS)->flatMap(function (string $class, string $kind): Collection {
      if (! Schema::hasTable((new $class)->getTable())) {
        return collect();
      }

      return $this->query($kind)->orderBy('created_at')->orderBy('id')->get()->map(fn ($record) => ['kind' => $kind, 'record' => $record]);
    })->values();
  }

  /** Include finished receipts attached to an unresolved operation, without the history limit. */
  public function runIds(): array
  {
    $ids = $this->query('run')->pluck('id')->all();
    foreach (['operation', 'request'] as $kind) {
      if (Schema::hasTable((new (self::MODELS[$kind]))->getTable())) {
        $ids = array_merge($ids, $this->query($kind)->whereNotNull('system_update_run_id')->pluck('system_update_run_id')->all());
      }
    }
    if (Schema::hasColumns('wbcms_update_chain_steps', ['operation_id', 'system_update_run_id']) && Schema::hasTable('wbcms_admin_update_operations')) {
      $ids = array_merge($ids, UpdateChainStep::whereIn('operation_id', $this->query('operation')->select('id'))->whereNotNull('system_update_run_id')->pluck('system_update_run_id')->all());
    }

    return array_values(array_unique($ids));
  }

  public function find(string $kind, string $id, bool $lock = false): Model
  {
    $class = self::MODELS[$kind];
    $query = $class::query()->whereKey($id);

    return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
  }

  /** Related SQL receipts are evaluated together; a supervisor dying does not prove its child stopped. */
  public function related(Model $record): Collection
  {
    if (! Schema::hasTable('wbcms_admin_update_operations') || ! Schema::hasTable('wbcms_system_update_requests')) {
      return collect([$record]);
    }
    $runs = $record instanceof SystemUpdateRun ? [$record->id] : array_filter([$record->system_update_run_id]);
    $operationIds = $record instanceof AdminUpdateOperation ? [$record->id] : [];
    if (Schema::hasColumns('wbcms_update_chain_steps', ['operation_id', 'system_update_run_id'])) {
      $operationIds = array_merge($operationIds, UpdateChainStep::whereIn('system_update_run_id', $runs)->pluck('operation_id')->all());
      $runs = array_merge($runs, UpdateChainStep::whereIn('operation_id', $operationIds)->whereNotNull('system_update_run_id')->pluck('system_update_run_id')->all());
    }
    $operations = AdminUpdateOperation::where(function ($query) use ($runs, $operationIds): void {
      $query->whereIn('system_update_run_id', $runs)->orWhereIn('id', $operationIds);
    })->get();
    $runs = array_unique(array_merge($runs, $operations->pluck('system_update_run_id')->filter()->all()));
    $requests = SystemUpdateRequest::whereIn('system_update_run_id', $runs)->get();

    return collect([$record])->concat(SystemUpdateRun::whereIn('id', $runs)->get())->concat($operations)->concat($requests)
      ->unique(fn (Model $item) => $item->getTable().':'.$item->getKey())->values();
  }

  public function requiresRecovery(): bool
  {
    foreach (self::MODELS as $class) {
      $table = (new $class)->getTable();
      if (Schema::hasTable($table) && Schema::hasColumn($table, 'recovery_required') && $class::where('recovery_required', true)->exists()) {
        return true;
      }
    }

    return false;
  }
}
