<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\UpdateChainStep;
use WebBlocks\Cms\Support\System\Updates\CmsChainContext;
use WebBlocks\Cms\Support\System\Updates\CmsPinnedChainClient;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\System\Updates\CmsUpdateAdmissionContext;
use WebBlocks\Cms\Support\System\Updates\CmsUpdateProgress;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;
use WebBlocks\Cms\Support\Updates\CmsUpdateMaintenance;

class RunUpdateChainStepCommand extends Command
{
  protected $signature = 'webblocks:updates:chain-step {operation} {position}';

  protected $hidden = true;

  public function handle(): int
  {
    $id = (string) $this->argument('operation');
    if (! Str::isUuid($id) || ! ctype_digit((string) $this->argument('position'))) {
      return self::FAILURE;
    }
    app(CmsPublisherClientConfigurator::class)->configure();
    $operation = AdminUpdateOperation::find($id);
    if (! $operation || $operation->chain_protocol !== 1 || $operation->status !== 'running'
      || $operation->recovery_required || ! $operation->heartbeat_at || $operation->heartbeat_at->lt(now()->subSeconds(90))
      || ! $operation->worker_pid || ! CmsUpdateMaintenance::active()) {
      return self::FAILURE;
    }
    $step = UpdateChainStep::where('operation_id', $id)->where('position', (int) $this->argument('position'))->first();
    if (! $step || $step->from_version !== $operation->last_verified_version || $step->status !== 'queued') {
      return self::FAILURE;
    }
    if (! UpdateChainStep::whereKey($step->id)->where('status', 'queued')->update(['status' => 'running', 'worker_pid' => getmypid(), 'started_at' => now()])) {
      return self::FAILURE;
    }
    app(CmsChainContext::class)->stepId = $step->id;
    app(CmsUpdateAdmissionContext::class)->operationId = $operation->id;
    config()->set('publisher-client.lock.name', config('publisher-client.lock.name').':chain:'.$id.':'.$step->position);
    // The supervisor owns maintenance and the public update lease across all steps.
    config()->set('publisher-client.apply.maintenance_mode', false);
    app(CmsUpdateProgress::class)->operation($id);
    try {
      if (! config('webblocks-updates.enabled', true) || ! config('publisher-client.enabled', true)) {
        throw new UpdateException(__('webblocks-cms::admin.update_chain.unavailable'));
      }
      if ($operation->user_id !== null) {
        $userClass = config('auth.providers.users.model');
        $user = $userClass::find($operation->user_id);
        if (! $user || ! Gate::forUser($user)->allows('access-system')) {
          throw new UpdateException(__('webblocks-cms::admin.update_chain.failed'));
        }
      }
      $engine = app()->makeWith(SystemUpdater::class, ['client' => new CmsPinnedChainClient($operation, $step)]);
      $result = $engine->run($operation->user_id === null ? null : (int) $operation->user_id);

      return in_array($result->status, ['success', 'success_with_warnings'], true) ? self::SUCCESS : self::FAILURE;
    } catch (\Throwable $exception) {
      $step->refresh();
      if ($step->status === 'running' && ! $step->system_update_run_id) {
        $step->update(['status' => 'failed', 'finished_at' => now()]);
      }
      report($exception);

      return self::FAILURE;
    } finally {
      app(CmsUpdateAdmissionContext::class)->operationId = null;
      app(CmsChainContext::class)->stepId = null;
    }
  }
}
