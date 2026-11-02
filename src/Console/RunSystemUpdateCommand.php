<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use WebBlocks\Cms\Actions\System\StartCmsUpdateChain;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\System\Updates\RunCmsUpdateChain;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;

class RunSystemUpdateCommand extends Command
{
  use ConfirmableTrait;

  protected $signature = 'webblocks:updates:run {--force}';

  public function getDescription(): string
  {
    return __('webblocks-cms::admin.update_cli.run_description');
  }

  public function handle(CmsPublisherClientConfigurator $configurator): int
  {
    if (! $this->confirmToProceed()) {
      return self::FAILURE;
    }

    // Restore the CMS-owned configuration before resolving the shared engine.
    $configurator->configure();
    try {
      if ((bool) config('webblocks-updates.chains.enabled', true)) {
        $operation = app(StartCmsUpdateChain::class)->execute(null, false);
        $success = app(RunCmsUpdateChain::class)->run($operation);
        $this->line((string) $operation->fresh()->summary);

        return $success ? self::SUCCESS : self::FAILURE;
      }
      $result = app(SystemUpdater::class)->run();
      $this->info($result->summary);

      return self::SUCCESS;
    } catch (\Throwable) {
      $this->error(__('webblocks-cms::admin.update_cli.failed'));

      return self::FAILURE;
    }
  }
}
