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
        $this->registerPluginAdminBridgeRoutes($plugin);

        if ($plugin->handle() !== 'webblocks-ui-manager') {
          $this->registerPluginAdminRoutes($plugin);
        }
      }
    }

    $this->registerPluginFallbackRoute();

    Route::getRoutes()->refreshNameLookups();
  }

  public function registerAdminRoutesFor(PluginDefinition $plugin): void
  {
    if ($plugin->handle() === 'webblocks-ui-manager') {
      $this->registerPluginAdminBridgeRoutes($plugin);
    } else {
      $this->registerPluginAdminRoutes($plugin);
    }

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

  private function registerPluginAdminBridgeRoutes(PluginDefinition $plugin): void
  {
    $base = trim($plugin->adminRoutePrefix(), '/');

    if ($plugin->handle() === 'webblocks-ui-manager') {
      if ($this->pluginBridgeRoutesRegistered($plugin)) {
        return;
      }

      Route::get($base.'/releases', PluginRouteFallbackController::class)
        ->defaults('plugin', $plugin->handle())
        ->defaults('pluginPath', 'releases')
        ->middleware($this->pluginBridgeMiddleware($plugin, 'webblocks-ui-manager.view'))
        ->name($plugin->routeNamePrefix().'.releases.index');

      Route::get($base.'/releases/create', PluginRouteFallbackController::class)
        ->defaults('plugin', $plugin->handle())
        ->defaults('pluginPath', 'releases/create')
        ->middleware($this->pluginBridgeMiddleware($plugin, 'webblocks-ui-manager.manage'))
        ->name($plugin->routeNamePrefix().'.releases.create');

      Route::post($base.'/releases', PluginRouteFallbackController::class)
        ->defaults('plugin', $plugin->handle())
        ->defaults('pluginPath', 'releases')
        ->middleware($this->pluginBridgeMiddleware($plugin, 'webblocks-ui-manager.manage'))
        ->name($plugin->routeNamePrefix().'.releases.store');

      Route::get($base.'/releases/{release}', PluginRouteFallbackController::class)
        ->defaults('plugin', $plugin->handle())
        ->defaults('pluginPath', 'releases/{release}')
        ->middleware($this->pluginBridgeMiddleware($plugin, 'webblocks-ui-manager.view'))
        ->name($plugin->routeNamePrefix().'.releases.show');

      Route::get($base.'/releases/{release}/edit', PluginRouteFallbackController::class)
        ->defaults('plugin', $plugin->handle())
        ->defaults('pluginPath', 'releases/{release}/edit')
        ->middleware($this->pluginBridgeMiddleware($plugin, 'webblocks-ui-manager.manage'))
        ->name($plugin->routeNamePrefix().'.releases.edit');

      Route::put($base.'/releases/{release}', PluginRouteFallbackController::class)
        ->defaults('plugin', $plugin->handle())
        ->defaults('pluginPath', 'releases/{release}')
        ->middleware($this->pluginBridgeMiddleware($plugin, 'webblocks-ui-manager.manage'))
        ->name($plugin->routeNamePrefix().'.releases.update');

      Route::post($base.'/releases/{release}/publish-dry-run', PluginRouteFallbackController::class)
        ->defaults('plugin', $plugin->handle())
        ->defaults('pluginPath', 'releases/{release}/publish-dry-run')
        ->middleware($this->pluginBridgeMiddleware($plugin, 'webblocks-ui-manager.publish'))
        ->name($plugin->routeNamePrefix().'.releases.publish.dry-run');

      Route::post($base.'/releases/{release}/publish', PluginRouteFallbackController::class)
        ->defaults('plugin', $plugin->handle())
        ->defaults('pluginPath', 'releases/{release}/publish')
        ->middleware($this->pluginBridgeMiddleware($plugin, 'webblocks-ui-manager.publish'))
        ->name($plugin->routeNamePrefix().'.releases.publish');

      if ($plugin->settingsDefinition()?->usesDefaultRoute()) {
        Route::get($base.'/settings', PluginRouteFallbackController::class)
          ->defaults('plugin', $plugin->handle())
          ->defaults('pluginPath', 'settings')
          ->middleware($this->pluginBridgeMiddleware($plugin, $this->settingsPermission($plugin)))
          ->name($this->defaultSettingsRouteName($plugin));
      }
    }
  }

  private function pluginBridgeRoutesRegistered(PluginDefinition $plugin): bool
  {
    $route = Route::getRoutes()->getByName($plugin->routeNamePrefix().'.releases.index');

    return $route?->getActionName() === PluginRouteFallbackController::class;
  }

  /**
   * @return array<int, string>
   */
  private function pluginBridgeMiddleware(PluginDefinition $plugin, string $permission): array
  {
    return [
      'web',
      'install.required',
      'auth',
      'admin.access',
      GuardPluginSetup::class.':'.$plugin->handle(),
      'plugin.permission:'.$permission,
    ];
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
