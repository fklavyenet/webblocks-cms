<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\Artisan;
use Throwable;

class PluginRuntimeRefresher
{
  /**
   * @return array{optimized_caches_cleared: bool, plugin_block_types: array{created: int, updated: int, unchanged: int, skipped: int}}
   */
  public function refresh(bool $clearOptimizedCaches = false, bool $registerRoutes = false): array
  {
    foreach ([
      PluginRegistry::class,
      PluginPermissionRegistry::class,
      PluginAuthorizationRegistrar::class,
      PluginAdminExtensionRegistry::class,
      PluginBlockCatalog::class,
      PluginBlockRegistry::class,
      PluginPublicAssetRegistry::class,
      PluginHealthMonitor::class,
    ] as $abstract) {
      app()->forgetInstance($abstract);
    }

    $cleared = false;

    if ($clearOptimizedCaches) {
      try {
        Artisan::call('optimize:clear');
        $cleared = true;
      } catch (Throwable) {
        $cleared = false;
      }
    }

    app(PluginAuthorizationRegistrar::class)->register();

    if ($registerRoutes) {
      app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    }

    /*
     * Resolved after the forget loop above, so the syncer reads the registry
     * that the lifecycle change just produced rather than the one it replaced.
     * Every plugin install, enable, disable, setup and update funnels through
     * here, which makes it the one place where a plugin's declared blocks can
     * be guaranteed a catalog row.
     */
    $blockTypes = app(PluginBlockTypeCatalogSyncer::class)->sync();

    return [
      'optimized_caches_cleared' => $cleared,
      'plugin_block_types' => $blockTypes,
    ];
  }
}
