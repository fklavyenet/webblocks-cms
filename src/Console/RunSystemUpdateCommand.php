<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
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
      $result = app(SystemUpdater::class)->run();
      $this->info($result->summary);

      return self::SUCCESS;
    } catch (\Throwable) {
      $this->error(__('webblocks-cms::admin.update_cli.failed'));

      return self::FAILURE;
    }
  }
}
