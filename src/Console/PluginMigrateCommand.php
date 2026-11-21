<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;
use WebBlocks\Cms\Support\Plugins\InstalledPluginDefinitionFactory;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Plugins\PluginBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\Plugins\PluginCompatibility;
use WebBlocks\Cms\Support\Plugins\PluginDatabaseSetup;
use WebBlocks\Cms\Support\Plugins\PluginMigrationRunner;

class PluginMigrateCommand extends Command
{
  protected $signature = 'cms:plugin-migrate {handle} {version} {--repair} {--sync-block-catalog}';

  protected $description = 'Apply pending database changes for one installed plugin version';

  public function handle(InstalledPluginRepository $plugins, InstalledPluginDefinitionFactory $factory, PluginMigrationRunner $migrations): int
  {
    try {
      $installed = $plugins->findVersion((string) $this->argument('handle'), (string) $this->argument('version'));
      if ($installed === null) {
        return self::FAILURE;
      }
      // Disabled installations also need their own classes for migrations.
      // Loading their definition here does not enable them in the host.
      $plugin = $factory->make($installed['manifest'], $installed['path'], false);
      try {
        app(PluginCompatibility::class)->assertCompatible($plugin);
      } catch (RuntimeException $exception) {
        $this->error($exception->getMessage());

        return self::FAILURE;
      }
      $plugin = $factory->make($installed['manifest'], $installed['path'], true);
      $repair = (bool) $this->option('repair');
      $syncBlockCatalog = (bool) $this->option('sync-block-catalog');
      if ($repair || ! $syncBlockCatalog || $migrations->hasPendingMigrations($plugin)) {
        $migrations->run($plugin, repairRecordedMigrations: $repair);
      }

      if ($migrations->hasPendingMigrations($plugin)) {
        return self::FAILURE;
      }
      if ($syncBlockCatalog) {
        DB::transaction(fn () => app(PluginBlockTypeCatalogSyncer::class)->sync(plugin: $plugin));
        $this->line(PluginDatabaseSetup::BLOCK_CATALOG_SYNCED);
      }

      return self::SUCCESS;
    } catch (Throwable) {
      $this->error(__('webblocks-cms::admin.system_plugins_show.database_update_failed'));

      return self::FAILURE;
    }
  }
}
