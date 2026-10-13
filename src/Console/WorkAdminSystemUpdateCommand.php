<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Support\System\Updates\AdminApprovedUpdateClient;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\System\Updates\CmsUpdateProgress;
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
      if ($operation->status !== 'queued') {
      return self::SUCCESS;
      }
      app(CmsPublisherClientConfigurator::class)->configure();
      $operation->update(['status' => 'running', 'worker_pid' => getmypid(), 'heartbeat_at' => now()]);
      app(CmsUpdateProgress::class)->operation($id);
      $engine = app()->makeWith(SystemUpdater::class, ['client' => new AdminApprovedUpdateClient(app(UpdateServerClient::class), $operation)]);
      try {
        $result = $engine->run((int) $operation->user_id);
        $operation->update(['status' => $result->status, 'summary' => $result->summary, 'finished_at' => $result->finishedAt, 'heartbeat_at' => now()]);

        return self::SUCCESS;
      } catch (Throwable $exception) {
        $operation->refresh();
        if (in_array($operation->status, ['queued', 'running'], true)) {
          $operation->update(['status' => 'failed', 'summary' => 'The update did not complete. Review its update log.', 'finished_at' => now()]);
        }
        report($exception);

        return self::FAILURE;
      }
    } finally {
    $lock->release();
    }
  }
}
