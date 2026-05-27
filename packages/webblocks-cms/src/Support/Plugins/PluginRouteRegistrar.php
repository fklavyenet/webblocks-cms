<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\Admin\PluginSettingsController;
use WebBlocks\Cms\Http\Middleware\GuardPluginSetup;

class PluginRouteRegistrar
{
  public function __construct(
    private readonly PluginRegistry $plugins,
  ) {}

  public function registerEnabledAdminRoutes(): void
  {
    if (app()->routesAreCached()) {
      return;
    }

    foreach ($this->plugins->enabled() as $plugin) {
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
    $this->definePluginPermissionGates($plugin);

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

  private function definePluginPermissionGates(PluginDefinition $plugin): void
  {
    foreach ($plugin->permissionsList() as $permission) {
      if (Gate::has($permission->name())) {
        continue;
      }

      Gate::define($permission->name(), fn ($user): bool => is_object($user) && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin());
    }
  }

  private function registerDefaultSettingsRoute(PluginDefinition $plugin): void
  {
    $settings = $plugin->settingsDefinition();

    if ($settings === null || ! $settings->usesDefaultRoute()) {
      return;
    }

    Route::get('/settings', [PluginSettingsController::class, 'edit'])
      ->defaults('plugin', $plugin->handle())
      ->middleware('can:access-system')
      ->name('settings.edit');
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
