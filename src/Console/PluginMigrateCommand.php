<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;
use WebBlocks\Cms\Support\Extensions\ExtensionOperation;
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
    $operation = new ExtensionOperation('plugin', 'setup');
    $handle = (string) $this->argument('handle');
    $version = (string) $this->argument('version');
    $operation->identify($handle, $version);
    try {
      $installed = $plugins->findVersion($handle, $version);
      if ($installed === null) {
        $this->error($operation->fail());

        return self::FAILURE;
      }
      // Disabled installations also need their own classes for migrations.
      // Loading their definition here does not enable them in the host.
      $plugin = $factory->make($installed['manifest'], $installed['path'], false);
      $operation->identify($handle, $version, $plugin->labelText());
      $operation->during('compatibility');
      try {
        app(PluginCompatibility::class)->assertCompatible($plugin);
      } catch (RuntimeException) {
        $this->error(trim($operation->fail().' '.app(PluginCompatibility::class)->diagnosticRequirement($plugin)));

        return self::FAILURE;
      }
      $operation->during('source');
      $plugin = $factory->make($installed['manifest'], $installed['path'], true);
      $operation->during('database');
      $repair = (bool) $this->option('repair');
      $syncBlockCatalog = (bool) $this->option('sync-block-catalog');
      if ($repair || ! $syncBlockCatalog || $migrations->hasPendingMigrations($plugin)) {
        $migrations->run($plugin, repairRecordedMigrations: $repair);
      }

      if ($migrations->hasPendingMigrations($plugin)) {
        $this->error($operation->fail());

        return self::FAILURE;
      }
      if ($syncBlockCatalog) {
        DB::transaction(fn () => app(PluginBlockTypeCatalogSyncer::class)->sync(plugin: $plugin));
        $this->line(PluginDatabaseSetup::BLOCK_CATALOG_SYNCED);
      }

      return self::SUCCESS;
    } catch (Throwable) {
      $this->error($operation->fail());

      return self::FAILURE;
    }
  }
}
