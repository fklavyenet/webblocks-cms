<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\SystemUpdateRequest;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Queries\System\UnfinishedSystemUpdates;

class SystemUpdateDetailRetention
{
  public const KEEP = 100;

  private const AUDIT_TABLE = 'wbcms_update_reconciliations';

  public function pruneReceiptLogs(): void
  {
    $unfinished = app(UnfinishedSystemUpdates::class);
    $receiptTable = (new SystemUpdateRequest)->getTable();
    if (! Schema::hasTable($receiptTable)) {
      return;
    }

    $receipts = SystemUpdateRequest::query()->whereIn('status', [
      SystemUpdateRun::STATUS_SUCCESS,
      SystemUpdateRun::STATUS_SUCCESS_WITH_WARNINGS,
      SystemUpdateRun::STATUS_FAILED,
      SystemUpdateRun::STATUS_RESTORED,
      SystemUpdateRun::STATUS_INTERRUPTED,
    ])->whereNotIn('id', $unfinished->query('request')->select('id'));

    // Count logs exposed by the receipt model, including legacy results.
    $seen = 0;
    foreach ((clone $receipts)->orderByDesc('created_at')->orderByDesc('id')->lazy(100) as $receipt) {
      if (isset($receipt->result['output']) && ++$seen > self::KEEP) {
        DB::transaction(function () use ($receipts, $receipt, $receiptTable): void {
          // A retained run can still supply the result through reconciliation.
          $current = (clone $receipts)->whereKey($receipt->id)->whereNotExists(function (Builder $run) use ($receiptTable): void {
            $model = new SystemUpdateRun;
            $run->selectRaw('1')->from($model->getTable())
              ->whereColumn($model->getQualifiedKeyName(), $receiptTable.'.system_update_run_id');
          })->lockForUpdate()->first();

          $result = $current?->result;
          if (isset($result['output'])) {
            unset($result['output']);
            $current->forceFill(['result' => $result])->save();
          }
        });
      }
    }
  }

  public function pruneAuditHistory(): void
  {
    if (! Schema::hasTable(self::AUDIT_TABLE)) {
      return;
    }

    $latest = DB::table(self::AUDIT_TABLE)->selectRaw('max(id) as id')->groupBy('record_type', 'record_id');
    $history = DB::table(self::AUDIT_TABLE.' as audit')
      ->join(self::AUDIT_TABLE.' as dismissal', function ($join): void {
        $join->on('dismissal.record_type', '=', 'audit.record_type')
          ->on('dismissal.record_id', '=', 'audit.record_id');
      })
      ->joinSub($latest, 'latest', 'latest.id', '=', 'dismissal.id')
      ->where('dismissal.status', 'dismissed')
      ->where('dismissal.recovery_required', false);

    $unfinished = app(UnfinishedSystemUpdates::class);
    $history->where(function (Builder $kinds) use ($unfinished): void {
      foreach (UnfinishedSystemUpdates::MODELS as $kind => $class) {
        $model = new $class;
        if (! Schema::hasTable($model->getTable())) {
          continue;
        }

        $kinds->orWhere(function (Builder $rows) use ($unfinished, $kind, $model): void {
          $key = $model->getQualifiedKeyName();
          $rows->where('audit.record_type', $kind)
            ->whereNotExists($unfinished->query($kind)->selectRaw('1')->whereColumn($key, 'audit.record_id')->toBase())
            ->where(function (Builder $detail) use ($model, $key): void {
              // The latest dismissal keeps an existing record off the attention list.
              $detail->whereColumn('audit.id', '<', 'dismissal.id')
                ->orWhereNotExists($model->newQuery()->selectRaw('1')->whereColumn($key, 'audit.record_id')->toBase());
            });
        });
      }
    });

    $keepIds = (clone $history)->orderByDesc('audit.id')->limit(self::KEEP)->pluck('audit.id');
    $history->whereNotIn('audit.id', $keepIds)->select('audit.id')
      ->chunkById(100, function (Collection $rows): void {
        DB::table(self::AUDIT_TABLE)->whereIn('id', $rows->pluck('id'))->delete();
      }, 'audit.id', 'id');
  }
}
