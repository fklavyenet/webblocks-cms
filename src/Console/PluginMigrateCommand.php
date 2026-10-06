<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Throwable;
use WebBlocks\Cms\Support\Plugins\InstalledPluginDefinitionFactory;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Plugins\PluginCompatibility;
use WebBlocks\Cms\Support\Plugins\PluginMigrationRunner;

class PluginMigrateCommand extends Command
{
  protected $signature = 'cms:plugin-migrate {handle} {version} {--repair}';

  protected $description = 'Apply pending database changes for one installed plugin version';

  public function handle(InstalledPluginRepository $plugins, InstalledPluginDefinitionFactory $factory, PluginMigrationRunner $migrations): int
  {
    foreach ($plugins->installed() as $installed) {
      if (($installed['manifest']['handle'] ?? null) !== $this->argument('handle')
        || ($installed['manifest']['version'] ?? null) !== $this->argument('version')) {
        continue;
      }

      try {
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
        $migrations->run($plugin, repairRecordedMigrations: (bool) $this->option('repair'));

        return $migrations->hasPendingMigrations($plugin) ? self::FAILURE : self::SUCCESS;
      } catch (Throwable) {
        $this->error(__('webblocks-cms::admin.system_plugins_show.database_update_failed'));

        return self::FAILURE;
      }
    }

    return self::FAILURE;
  }
}
