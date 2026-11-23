<?php

namespace WebBlocks\Cms\Actions\Plugins;

use Illuminate\Support\Facades\Cache;
use RuntimeException;
use WebBlocks\Cms\Support\Extensions\ExtensionOperation;
use WebBlocks\Cms\Support\Plugins\InstalledPluginDefinitionFactory;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Plugins\PluginBootProbe;
use WebBlocks\Cms\Support\Plugins\PluginRuntimeRefresher;

class RecoverPlugin
{
  public function handle(string $handle, string $operation, ?ExtensionOperation $diagnostic = null): void
  {
    $plugins = app(InstalledPluginRepository::class);
    $lock = Cache::lock('webblocks-plugin-install-'.hash('sha256', $plugins->rootPath().'/'.$handle), 180);
    $diagnostic?->during('busy');
    if (! $lock->get()) {
      throw new RuntimeException(__('webblocks-cms::admin.system_plugins_show.database_update_busy'));
    }
    try {
      abort_unless($plugins->hasHandle($handle), 404);
      $diagnostic?->during('activation');
      if ($operation === 'disable') {
        $plugins->disable($handle, force: true);

        return;
      }
      $diagnostic?->during('rollback');
      $previous = $plugins->previous($handle);
      $version = is_string($previous['version'] ?? null) ? $previous['version'] : null;
      $diagnostic?->identify($handle, $version);
      if (($previous['rollback_safe'] ?? false) !== true || $version === null) {
        throw new RuntimeException(__('webblocks-cms::admin.plugin_recovery.restore_unsafe'));
      }
      $installed = $plugins->findVersion($handle, $version);
      if ($installed === null) {
        throw new RuntimeException(__('webblocks-cms::admin.plugin_recovery.restore_unsafe'));
      }
      $diagnostic?->identify($handle, $installed['manifest']['version'], $installed['manifest']['label'] ?? null);
      $diagnostic?->during('startup');
      $definition = app(InstalledPluginDefinitionFactory::class)->make($installed['manifest'], $installed['path'], false);
      app(PluginBootProbe::class)->check($definition);
      $diagnostic?->during('activation');
      $plugins->enable($handle, $version);
      $diagnostic?->during('runtime');
      app(PluginRuntimeRefresher::class)->refreshInstalledPackageAssets($handle, $version, $installed['path']);
      app(PluginRuntimeRefresher::class)->clearCompiledViews();
    } finally {
      $lock->release();
    }
  }
}
