<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\SystemUpdateRequest;
use WebBlocks\Cms\Support\System\Updates\AdminApprovedUpdateClient;
use WebBlocks\Cms\Support\System\Updates\ApiUpdateRunRecorder;
use WebBlocks\Cms\Support\System\Updates\ApprovedUpdateTargetChanged;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\System\Updates\CmsUpdateAdmissionContext;
use WebBlocks\Cms\Support\System\Updates\CmsUpdateProgress;
use WebBlocks\Cms\Support\System\Updates\RunCmsUpdateChain;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateRunReconciler;
use WebBlocks\Cms\Support\System\Updates\UpdateWorkerEvidence;
use WebBlocks\Cms\Support\Updates\Client\Contracts\RunRecorder;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateServerClient;

class WorkAdminSystemUpdateCommand extends Command
{
  protected $signature = 'webblocks:updates:work {operation}';

  protected $description = 'Execute one admitted panel update outside the HTTP request lifetime';

  public function handle(): int
  {
    $id = (string) $this->argument('operation');
    if (! Str::isUuid($id)) {
    return self::FAILURE;
    }
    $lock = Cache::lock('system-updates:worker:'.$id, 86400);
    if (! $lock->get()) {
    return self::FAILURE;
    }
    try {
      $operation = AdminUpdateOperation::findOrFail($id);
      app(CmsUpdateAdmissionContext::class)->operationId = $operation->id;
      if ($operation->chain_protocol === 1) {
        app(CmsPublisherClientConfigurator::class)->configure();

        return app(RunCmsUpdateChain::class)->run($operation) ? self::SUCCESS : self::FAILURE;
      }
      if ($operation->status !== 'queued') {
      return self::SUCCESS;
      }
      app(CmsPublisherClientConfigurator::class)->configure();
      $operation->update(['status' => 'running', 'worker_pid' => getmypid(), 'heartbeat_at' => now()]
        + app(UpdateWorkerEvidence::class)->capture('wbcms_admin_update_operations'));
      app(CmsUpdateProgress::class)->operation($id);
      $receipt = SystemUpdateRequest::where('admin_update_operation_id', $id)->first();
      $parameters = ['client' => new AdminApprovedUpdateClient(app(UpdateServerClient::class), $operation)];
      if ($receipt) {
        app(CmsUpdateAdmissionContext::class)->requestId = $receipt->id;
        $receipt->update(['status' => 'running'] + app(UpdateWorkerEvidence::class)->capture($receipt->getTable()));
        $parameters['runRecorder'] = new ApiUpdateRunRecorder(app(RunRecorder::class), $receipt);
      }
      $engine = app()->makeWith(SystemUpdater::class, $parameters);
      try {
        $result = $engine->run((int) $operation->user_id);
        $operation->update(['status' => $result->status, 'summary' => $result->summary, 'finished_at' => $result->finishedAt, 'heartbeat_at' => now()]);

        return self::SUCCESS;
      } catch (Throwable $exception) {
        $operation->refresh();
        if (in_array($operation->status, ['queued', 'running'], true)) {
          $operation->update(['status' => 'failed', 'summary' => 'The update did not complete. Review its update log.', 'finished_at' => now()]);
        }
        if ($receipt) {
          app(SystemUpdateRunReconciler::class)->reconcileReceipt($receipt);
          if (in_array($receipt->status, ['queued', 'running'], true)) {
            $receipt->update(['status' => $receipt->system_update_run_id ? 'interrupted' : 'failed',
              'recovery_required' => $receipt->system_update_run_id !== null,
              'code' => $exception instanceof ApprovedUpdateTargetChanged ? 'system_update_target_changed' : 'system_update_failed', 'finished_at' => now()]);
          }
        }
        report($exception);

        return self::FAILURE;
      }
    } finally {
    app(CmsUpdateAdmissionContext::class)->operationId = null;
    app(CmsUpdateAdmissionContext::class)->requestId = null;
    $lock->release();
    }
  }
}
