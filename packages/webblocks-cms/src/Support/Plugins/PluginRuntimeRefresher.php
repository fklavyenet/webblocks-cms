<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\Artisan;
use Throwable;

class PluginRuntimeRefresher
{
  /**
   * @return array{optimized_caches_cleared: bool}
   */
  public function refresh(bool $clearOptimizedCaches = false, bool $registerRoutes = false): array
  {
    foreach ([
      PluginRegistry::class,
      PluginPermissionRegistry::class,
      PluginAuthorizationRegistrar::class,
      PluginAdminExtensionRegistry::class,
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

    return [
      'optimized_caches_cleared' => $cleared,
    ];
  }
}
