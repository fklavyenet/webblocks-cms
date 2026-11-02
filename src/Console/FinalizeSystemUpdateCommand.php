<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Cache;
use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\System\Updates\CmsChainAdmission;
use WebBlocks\Cms\Support\System\Updates\CmsComposerState;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Support\System\Updates\UpdateInstaller;
use WebBlocks\Cms\Support\Updates\Client\Updates\RunHeartbeat;
use WebBlocks\Cms\Support\WebBlocks;

class FinalizeSystemUpdateCommand extends Command
{
  use ConfirmableTrait;

  protected $signature = 'webblocks:updates:finalize {--force}';

  public function getDescription(): string
  {
    return __('webblocks-cms::admin.update_cli.finalize_description');
  }

  public function handle(CmsPublisherClientConfigurator $configurator): int
  {
    if (! $this->confirmToProceed()) {
      return self::FAILURE;
    }

    $configurator->configure();
    if (app(CmsChainAdmission::class)->blocked()) {
      $this->error(__('webblocks-cms::admin.update_chain.locked'));

      return self::FAILURE;
    }
    $lock = Cache::lock((string) config('publisher-client.lock.name'), (int) config('publisher-client.lock.ttl_seconds', 7200));
    if (! $lock->get()) {
      $this->error(__('webblocks-cms::admin.update_cli.locked'));

      return self::FAILURE;
    }

    $heartbeat = app(RunHeartbeat::class);
    try {
      $heartbeat->beat();
      app(CmsComposerState::class)->assertCurrent();
      $installer = app(UpdateInstaller::class);
      $output = [];
      $installer->installDependencies($output);
      $installer->runPostInstallCommands($output);
      $installer->syncRuntimeAssets($output);
      app(InstalledVersionStore::class)->persist(WebBlocks::version());
      $this->info(__('webblocks-cms::admin.update_cli.finalized'));

      return self::SUCCESS;
    } catch (UpdateException $exception) {
      $this->error($exception->userMessage());

      return self::FAILURE;
    } catch (\Throwable) {
      $this->error(__('webblocks-cms::admin.update_cli.finalize_failed'));

      return self::FAILURE;
    } finally {
      try {
        $heartbeat->clear();
      } finally {
        $lock->release();
      }
    }
  }
}
