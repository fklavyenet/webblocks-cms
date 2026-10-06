<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateCommandRunner;

class PluginDatabaseSetup
{
  public function __construct(
    private readonly PluginMigrationRunner $migrations,
    private readonly InstalledPluginRepository $plugins,
  ) {}

  /** @return array{status: string, ran: bool, paths_count: int} */
  public function run(PluginDefinition $plugin, bool $repairRecordedMigrations = false): array
  {
    app(PluginCompatibility::class)->assertCompatible($plugin);

    $version = $plugin->versionText();
    if ($version === null) {
      throw new RuntimeException('Plugin version is missing.');
    }

    $timeout = max(1, (int) config('webblocks-plugins.install.database_timeout_seconds', 120));
    $lock = Cache::lock('webblocks-plugin-database-'.hash('sha256', $this->plugins->rootPath().'/'.$plugin->handle()), $timeout + 30);
    if (! $lock->get()) {
      throw new RuntimeException(__('webblocks-cms::admin.system_plugins_show.database_update_busy'));
    }
    try {
      $this->plugins->recordSetupResult($plugin->handle(), $version, ['status' => 'running']);
      $pending = $repairRecordedMigrations || $this->migrations->hasPendingMigrations($plugin);
      if ($pending) {
        // A fresh CLI process loads the installed version, never the old provider
        // already present in the PHP request that replaced the package.
        $result = Process::path(base_path())
          ->timeout($timeout)
          ->env(['WEBBLOCKS_PLUGIN_INSTALL_ROOT' => $this->plugins->rootPath()])
          ->run((new UpdateCommandRunner)->artisanCommand([
            'cms:plugin-migrate', $plugin->handle(), $version, '--no-interaction',
            ...($repairRecordedMigrations ? ['--repair'] : []),
          ]));

        if (! $result->successful() || $this->migrations->hasPendingMigrations($plugin)) {
          throw new RuntimeException('Plugin database update did not complete.');
        }
      }

      $setup = ['status' => 'completed', 'ran' => $pending, 'paths_count' => count($plugin->migrationPaths())];
      $this->plugins->recordSetupResult($plugin->handle(), $version, $setup);

      return $setup;
    } catch (Throwable $exception) {
      $this->plugins->disable($plugin->handle());
      $this->plugins->recordSetupResult($plugin->handle(), $version, ['status' => 'failed']);

      // Subprocess output and SQL exceptions can contain host secrets. Keep the
      // operator-facing failure independent of those diagnostic details.
      throw new RuntimeException(__('webblocks-cms::admin.system_plugins_show.database_update_failed'), previous: $exception);
    } finally {
      $lock->release();
    }
  }
}
