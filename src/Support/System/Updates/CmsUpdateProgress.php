<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\Updates\Client\Updates\RunHeartbeat;

class CmsUpdateProgress
{
  private ?int $runId = null;

  public function start(SystemUpdateRun $run): void
  {
    $this->runId = (int) $run->id;
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

  public function payload(): array
  {
    if (! Schema::hasTable('wbcms_system_update_runs')) {
      return ['running' => false];
    }
    $run = SystemUpdateRun::latest('id')->first();
    if ($run === null) {
      return ['running' => false];
    }
    $step = Schema::hasTable('wbcms_system_update_run_steps')
      ? DB::table('wbcms_system_update_run_steps')->where('system_update_run_id', $run->id)->orderByDesc('id')->first() : null;
    $stage = $step?->stage;

    return [
      'running' => $run->status === SystemUpdateRun::STATUS_RUNNING && ! app(RunHeartbeat::class)->isStale(),
      'run_id' => $run->id,
      'duration_ms' => $run->duration_ms,
      'elapsed_ms' => $run->duration_ms ?? ($run->started_at ? max(0, (int) $run->started_at->diffInMilliseconds(now())) : null),
      'stage' => $stage,
      'stage_label' => $stage ? __('webblocks-cms::admin.updates.phases.'.$stage) : null,
    ];
  }
}
