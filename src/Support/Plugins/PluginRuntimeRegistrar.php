<?php

namespace WebBlocks\Cms\Support\Plugins;

class PluginRuntimeRegistrar
{
  public function register(): void
  {
    // Core boot hooks inspect named routes before plugin providers start.
    app('router')->getRoutes()->refreshNameLookups();
    app()->booted(function (): void {
      app(PluginProviderRegistrar::class)->register();
      $this->registerRoutes();
    });
  }

  private function registerRoutes(): void
  {
    $admin = app(PluginRouteRegistrar::class);
    if (! app()->routesAreCached()) {
      foreach (app(PluginRegistry::class)->enabled() as $plugin) {
        app(PluginRuntimeGuard::class)->routes($plugin, function () use ($admin, $plugin): void {
          $admin->registerAdminRoutesFor($plugin);
          app(PluginApiRouteRegistrar::class)->registerApiRoutesFor($plugin);
          app(PluginPublicRouteRegistrar::class)->registerPublicRoutesFor($plugin);
        });
      }
    }
    $admin->finishRegistration();
  }
}
