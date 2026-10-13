<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\Updates\Client\Updates\RunHeartbeat;
use WebBlocks\Cms\Support\WebBlocks;

class CmsUpdateProgress
{
  private ?int $runId = null;

  private ?string $operationId = null;

  private float $lastBeat = 0;

  public function operation(string $id): void
  {
  $this->operationId = $id;
  }

  public function beat(): void
  {
    if ($this->operationId === null || microtime(true) - $this->lastBeat < 5) {
    return;
    }
    $this->lastBeat = microtime(true);
    AdminUpdateOperation::whereKey($this->operationId)->update(['heartbeat_at' => now()]);
  }

  public function start(SystemUpdateRun $run): void
  {
    $this->runId = (int) $run->id;
    if ($this->operationId !== null) {
      AdminUpdateOperation::whereKey($this->operationId)->update(['system_update_run_id' => $run->id]);
      $this->beat();
    }
  }

  public function completed(string $status, string $summary): void
  {
    if ($this->operationId !== null) {
      AdminUpdateOperation::whereKey($this->operationId)->update(['status' => $status, 'summary' => $summary, 'finished_at' => now(), 'heartbeat_at' => now()]);
    }
  }

  public function stage(string $stage): void
  {
    if ($this->runId === null || ! Schema::hasTable('wbcms_system_update_run_steps')) {
      return;
    }
    $this->finish();
    DB::table('wbcms_system_update_run_steps')->insert(['system_update_run_id' => $this->runId, 'stage' => $stage, 'started_at' => now()->format('Y-m-d H:i:s.v'), 'finished_at' => null, 'duration_ms' => null]);
  }

  public function finish(): void
  {
    if ($this->runId === null || ! Schema::hasTable('wbcms_system_update_run_steps')) {
      return;
    }
    $step = DB::table('wbcms_system_update_run_steps')->where('system_update_run_id', $this->runId)->whereNull('finished_at')->first();
    if ($step !== null) {
      DB::table('wbcms_system_update_run_steps')->where('id', $step->id)->update(['finished_at' => now()->format('Y-m-d H:i:s.v'), 'duration_ms' => max(0, (int) Carbon::parse($step->started_at)->diffInMilliseconds(now()))]);
    }
  }

  public function payload(?string $operationId = null): array
  {
    $operation = Schema::hasTable('wbcms_admin_update_operations')
      ? ($operationId ? AdminUpdateOperation::find($operationId) : (AdminUpdateOperation::whereIn('status', ['queued', 'running'])->first() ?? AdminUpdateOperation::latest('started_at')->first())) : null;
    $run = $operation ? ($operation->system_update_run_id ? SystemUpdateRun::find($operation->system_update_run_id) : null)
      : (Schema::hasTable('wbcms_system_update_runs') ? SystemUpdateRun::latest('id')->first() : null);
    if (! $operationId) {
      $latest = Schema::hasTable('wbcms_system_update_runs') ? SystemUpdateRun::latest('id')->first() : null;
      if ($latest && (! $operation || ($operation->system_update_run_id !== null && $latest->id > $operation->system_update_run_id))) {
        $operation = null;
        $run = $latest;
      }
    }
    if ($operationId && ! $operation) {
    return ['state' => 'unknown', 'running' => false, 'terminal' => false];
    }
    $status = $operation?->status ?? $run?->status;
    $active = in_array($status, ['queued', 'running'], true);
    $alive = $operation
      ? ($operation->heartbeat_at ? ($operation->heartbeat_at->gt(now()->subSeconds(90)) || ! app(RunHeartbeat::class)->isStale()) : $operation->started_at->gt(now()->subSeconds(60)))
      : ! app(RunHeartbeat::class)->isStale();
    $state = $active ? ($alive ? $status : 'interrupted') : ($status ?? 'idle');
    $terminal = in_array($state, ['success', 'success_with_warnings', 'failed', 'restored', 'cancelled'], true);
    $step = $run && Schema::hasTable('wbcms_system_update_run_steps')
      ? DB::table('wbcms_system_update_run_steps')->where('system_update_run_id', $run->id)->orderByDesc('id')->first() : null;
    $started = $operation?->started_at ?? $run?->started_at;

    return [
      'state' => $state, 'running' => $active && $alive, 'terminal' => $terminal,
      'operation_id' => $operation?->id, 'run_id' => $run?->id,
      'target_version' => $operation?->target_version ?? $run?->to_version,
      'installed_version' => WebBlocks::version(),
      'duration_ms' => $run?->duration_ms,
      'elapsed_ms' => $run?->duration_ms ?? ($started ? max(0, (int) $started->diffInMilliseconds($operation?->finished_at ?? $run?->finished_at ?? now())) : null),
      'stage' => $step?->stage,
      'stage_label' => $step?->stage ? __('webblocks-cms::admin.updates.phases.'.$step->stage) : null,
    ];
  }
}
