<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\Route;

/**
 * Mounts enabled plugins' own internal API route files under the CMS internal
 * API group, so a plugin can expose token-authenticated, capability-guarded
 * endpoints to AI agents without the CMS hardcoding them.
 *
 * The group mirrors the core internal API in routes/admin.php: `/webadmin/api`
 * prefix, token auth, internal-content-api throttle, CSRF disabled (bearer-token
 * clients, not browser forms). Individual capability guards are declared per
 * route inside the plugin's route file via `internal-api.capability:<cap>`.
 */
class PluginApiRouteRegistrar
{
  /**
   * @var array<int, string>
   */
  private const GROUP_MIDDLEWARE = [
    'web',
    'install.required',
    'throttle:internal-content-api',
    'internal-api.token',
  ];

  /**
   * @var array<int, string>
   */
  private const CSRF_MIDDLEWARE = [
    'App\\Http\\Middleware\\VerifyCsrfToken',
    'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
    'Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken',
    'Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken',
  ];

  public function __construct(
    private readonly PluginRegistry $plugins,
  ) {}

  public function registerEnabledApiRoutes(): void
  {
    if (app()->routesAreCached()) {
      return;
    }

    foreach ($this->plugins->enabled() as $plugin) {
      if ($plugin->apiRouteDefinitions() === []) {
        continue;
      }

      $this->registerApiRoutesFor($plugin);
    }

    Route::getRoutes()->refreshNameLookups();
  }

  public function registerApiRoutesFor(PluginDefinition $plugin): void
  {
    Route::middleware(self::GROUP_MIDDLEWARE)
      ->withoutMiddleware(self::CSRF_MIDDLEWARE)
      ->prefix('webadmin/api')
      ->name('internal-content-api.')
      ->group(function () use ($plugin) {
        foreach ($plugin->apiRouteDefinitions() as $routes) {
          $this->registerRouteDefinition($plugin, $routes);
        }
      });

    Route::getRoutes()->refreshNameLookups();
  }

  private function registerRouteDefinition(PluginDefinition $plugin, string|callable $routes): void
  {
    if (is_callable($routes)) {
      $routes($plugin);

      return;
    }

    if (! is_file($routes)) {
      throw new PluginException("Plugin [{$plugin->handle()}] api route file [{$routes}] does not exist.");
    }

    require $routes;
  }
}
