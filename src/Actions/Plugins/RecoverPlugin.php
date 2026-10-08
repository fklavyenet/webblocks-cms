<?php

namespace WebBlocks\Cms\Actions\Plugins;

use Illuminate\Support\Facades\Cache;
use RuntimeException;
use WebBlocks\Cms\Support\Plugins\InstalledPluginDefinitionFactory;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Plugins\PluginBootProbe;
use WebBlocks\Cms\Support\Plugins\PluginRuntimeRefresher;

class RecoverPlugin
{
  public function handle(string $handle, string $operation): void
  {
    $plugins = app(InstalledPluginRepository::class);
    $lock = Cache::lock('webblocks-plugin-install-'.hash('sha256', $plugins->rootPath().'/'.$handle), 180);
    if (! $lock->get()) {
      throw new RuntimeException(__('webblocks-cms::admin.system_plugins_show.database_update_busy'));
    }
    try {
      abort_unless($plugins->hasHandle($handle), 404);
      if ($operation === 'disable') {
        $plugins->disable($handle);

        return;
      }
      $previous = $plugins->previous($handle);
      if (($previous['rollback_safe'] ?? false) !== true || ! is_string($previous['version'] ?? null)) {
        throw new RuntimeException(__('webblocks-cms::admin.plugin_recovery.restore_unsafe'));
      }
      $installed = $plugins->findVersion($handle, $previous['version']);
      if ($installed === null) {
        throw new RuntimeException(__('webblocks-cms::admin.plugin_recovery.restore_unsafe'));
      }
      $definition = app(InstalledPluginDefinitionFactory::class)->make($installed['manifest'], $installed['path'], false);
      app(PluginBootProbe::class)->check($definition);
      $plugins->enable($handle, $previous['version']);
      app(PluginRuntimeRefresher::class)->refreshInstalledPackageAssets($handle, $previous['version'], $installed['path']);
      app(PluginRuntimeRefresher::class)->clearCompiledViews();
    } finally {
      $lock->release();
    }
  }
}
