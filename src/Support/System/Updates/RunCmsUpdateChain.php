<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Facades\Cache;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\UpdateChainStep;
use WebBlocks\Cms\Support\Updates\Client\Chains\ChainCoordinator;
use WebBlocks\Cms\Support\Updates\Client\Chains\UpdatePlan;
use WebBlocks\Cms\Support\Updates\Client\Updates\RunHeartbeat;
use WebBlocks\Cms\Support\Updates\CmsUpdateMaintenance;

class RunCmsUpdateChain
{
  public function run(AdminUpdateOperation $operation): bool
  {
    $operation->refresh();
    if ($operation->status !== 'queued') {
      return in_array($operation->status, ['success', 'success_with_warnings'], true);
    }
    $lock = Cache::lock((string) config('publisher-client.lock.name'), 86400);
    if (! $lock->get()) {
      AdminUpdateOperation::whereKey($operation->id)->where('chain_protocol', 1)->where('status', 'queued')
        ->update(['status' => 'failed', 'summary' => __('webblocks-cms::admin.update_chain.locked'), 'finished_at' => now()]);

      return false;
    }
    try {
      if (! AdminUpdateOperation::whereKey($operation->id)->where('chain_protocol', 1)->where('status', 'queued')
        ->update(['status' => 'running', 'worker_pid' => getmypid(), 'heartbeat_at' => now()])) {
        return in_array($operation->fresh()->status, ['success', 'success_with_warnings'], true);
      }
      $operation->refresh();
      app(CmsChainContext::class)->supervising = true;
      app(CmsUpdateProgress::class)->operation($operation->id);
      app(RunHeartbeat::class)->beat();
      $steps = UpdateChainStep::where('operation_id', $operation->id)->orderBy('position')->get();
      $plan = new UpdatePlan($operation->product, $operation->channel, $operation->from_version,
        $operation->target_version, $steps->map(fn ($step) => $step->release($operation))->all());
      if ($plan->product !== config('publisher-client.product') || $plan->channel !== config('publisher-client.channel')) {
        throw new ApprovedUpdateTargetChanged;
      }
      $runner = app(UpdateCommandRunner::class);
      $output = [];
      if (! CmsUpdateMaintenance::active()) {
        $operation->update(['maintenance_owned' => true]);
        $runner->run($runner->artisanCommand(CmsUpdateMaintenance::arguments()), base_path(), $output);
      }
      app(ChainCoordinator::class)->run($plan, app()->makeWith(CmsChainStepExecutor::class, ['operation' => $operation]));
      $operation->refresh();
      $this->leaveMaintenance($operation);
      $warnings = UpdateChainStep::where('operation_id', $operation->id)->where('status', 'success_with_warnings')->exists();
      $operation->update(['status' => $warnings ? 'success_with_warnings' : 'success',
        'summary' => __('webblocks-cms::admin.update_chain.complete', ['version' => $plan->targetVersion]),
        'finished_at' => now(), 'heartbeat_at' => now()]);

      return true;
    } catch (\Throwable $exception) {
      $operation->refresh();
      $step = UpdateChainStep::where('operation_id', $operation->id)->where('status', '!=', 'queued')->orderByDesc('position')->first();
      $safe = $step && ($step->status === 'restored' || ($step->status === 'failed' && ! $step->system_update_run_id));
      // A fresh process verifies the checkpoint after rollback, before maintenance can end.
      if ($safe) {
        try {
          $output = [];
          $runner = app(UpdateCommandRunner::class);
          $runner->run($runner->artisanCommand(['webblocks:updates:chain-verify', $operation->id, (string) $step->position, '--checkpoint']), base_path(), $output);
          $this->leaveMaintenance($operation);
        } catch (\Throwable) {
          $safe = false;
        }
      }
      if (! $safe && $operation->maintenance_owned) {
        try {
          $output = [];
          $runner = app(UpdateCommandRunner::class);
          $runner->run($runner->artisanCommand(CmsUpdateMaintenance::arguments()), base_path(), $output);
        } catch (\Throwable $recovery) {
          report($recovery);
        }
      }
      try {
        $operation->update(['status' => $safe ? ($step->status === 'restored' ? 'restored' : 'failed') : 'interrupted',
          'recovery_required' => ! $safe, 'finished_at' => now(), 'heartbeat_at' => now(),
          'summary' => __('webblocks-cms::admin.update_chain.'.($safe ? 'stopped' : 'interrupted'), ['version' => $operation->last_verified_version])]);
      } catch (\Throwable $receiptFailure) {
        // A checkpoint without its durable receipt cannot authorize another update.
        // The still-running SQL operation continues to block admission.
        if ($operation->maintenance_owned) {
          $output = [];
          $runner = app(UpdateCommandRunner::class);
          $runner->run($runner->artisanCommand(CmsUpdateMaintenance::arguments()), base_path(), $output);
        }
        throw $receiptFailure;
      }
      report($exception);

      return false;
    } finally {
      app(CmsChainContext::class)->supervising = false;
      app(RunHeartbeat::class)->clear();
      $lock->release();
    }
  }

  private function leaveMaintenance(AdminUpdateOperation $operation): void
  {
    if ($operation->maintenance_owned) {
      $output = [];
      $runner = app(UpdateCommandRunner::class);
      $runner->run($runner->artisanCommand(['up']), base_path(), $output);
    }
  }
}
