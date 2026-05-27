<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\Admin\PluginRouteFallbackController;
use WebBlocks\Cms\Http\Controllers\Admin\PluginSettingsController;
use WebBlocks\Cms\Http\Middleware\GuardPluginSetup;

class PluginRouteRegistrar
{
  private PluginAuthorizationRegistrar $authorization;

  public function __construct(
    private readonly PluginRegistry $plugins,
    ?PluginAuthorizationRegistrar $authorization = null,
  ) {
    $this->authorization = $authorization ?? app(PluginAuthorizationRegistrar::class);
  }

  public function registerEnabledAdminRoutes(): void
  {
    if (! app()->routesAreCached()) {
      foreach ($this->plugins->enabled() as $plugin) {
        $this->registerPluginAdminRoutes($plugin);
      }
    }

    $this->registerPluginFallbackRoute();

    Route::getRoutes()->refreshNameLookups();
  }

  public function registerAdminRoutesFor(PluginDefinition $plugin): void
  {
    $this->registerPluginAdminRoutes($plugin);
    Route::getRoutes()->refreshNameLookups();
  }

  public function defaultSettingsRouteName(PluginDefinition $plugin): string
  {
    return $plugin->routeNamePrefix().'.settings.edit';
  }

  private function registerPluginAdminRoutes(PluginDefinition $plugin): void
  {
    $this->authorization->register();

    Route::middleware(['web', 'install.required', 'auth', 'admin.access'])
      ->middleware(GuardPluginSetup::class.':'.$plugin->handle())
      ->prefix(ltrim($plugin->adminRoutePrefix(), '/'))
      ->name($plugin->routeNamePrefix().'.')
      ->group(function () use ($plugin) {
        $this->registerDefaultSettingsRoute($plugin);

        foreach ($plugin->adminRouteDefinitions() as $routes) {
          $this->registerRouteDefinition($plugin, $routes);
        }
      });
  }

  private function registerPluginFallbackRoute(): void
  {
    if (Route::has('webblocks.plugins.fallback')) {
      return;
    }

    Route::any('webadmin/plugins/{plugin}/{pluginPath?}', PluginRouteFallbackController::class)
      ->where('pluginPath', '.*')
      ->middleware(['web', 'install.required', 'auth', 'admin.access'])
      ->name('webblocks.plugins.fallback');
  }

  private function registerDefaultSettingsRoute(PluginDefinition $plugin): void
  {
    $settings = $plugin->settingsDefinition();

    if ($settings === null || ! $settings->usesDefaultRoute()) {
      return;
    }

    Route::get('/settings', [PluginSettingsController::class, 'edit'])
      ->defaults('plugin', $plugin->handle())
      ->middleware('plugin.permission:'.$this->settingsPermission($plugin))
      ->name('settings.edit');
  }

  private function settingsPermission(PluginDefinition $plugin): string
  {
    $managePermission = $plugin->handle().'.manage';

    return array_key_exists($managePermission, $plugin->permissionsList())
      ? $managePermission
      : 'access-system';
  }

  private function registerRouteDefinition(PluginDefinition $plugin, string|callable $routes): void
  {
    if (is_callable($routes)) {
      $routes($plugin);

      return;
    }

    if (! is_file($routes)) {
      throw new PluginException("Plugin [{$plugin->handle()}] admin route file [{$routes}] does not exist.");
    }

    require $routes;
  }
}
