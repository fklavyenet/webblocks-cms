<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\Route;

/**
 * Mounts enabled plugins' own public route files under a reserved public
 * prefix, so a plugin can own a visitor-facing surface without the CMS
 * hardcoding its endpoints the way `routes/public.php` hardcodes the commerce
 * bridge today.
 *
 * Every plugin route lands under `/plugins/{handle}` with route names under
 * `webblocks.plugins.{plugin_handle}.public.*`. The prefix is a reserved first
 * segment, so a plugin endpoint can never shadow a page slug, and the existing
 * core-route protection reserves it from the redirect-manager catch-all
 * automatically once a route is registered.
 *
 * Unlike the internal API group, CSRF stays on: these serve browser forms and
 * same-origin fetches from block scripts, not bearer-token clients. The group
 * throttle is applied by the registrar rather than left to the plugin, so an
 * unthrottled public write surface is not something a plugin can ship by
 * omission. Plugin routes remain free to add a stricter per-route throttle.
 */
class PluginPublicRouteRegistrar
{
  /**
   * @var array<int, string>
   */
  private const GROUP_MIDDLEWARE = [
    'web',
    'install.required',
    'throttle:plugin-public-routes',
  ];

  public function __construct(
    private readonly PluginRegistry $plugins,
  ) {}

  public function registerEnabledPublicRoutes(): void
  {
    if (app()->routesAreCached()) {
      return;
    }

    foreach ($this->plugins->enabled() as $plugin) {
      if ($plugin->publicRouteDefinitions() === []) {
        continue;
      }

      $this->registerPublicRoutesFor($plugin);
    }

    Route::getRoutes()->refreshNameLookups();
  }

  public function registerPublicRoutesFor(PluginDefinition $plugin): void
  {
    Route::middleware(self::GROUP_MIDDLEWARE)
      ->prefix(ltrim($plugin->publicRoutePrefix(), '/'))
      ->name($plugin->publicRouteNamePrefix().'.')
      ->group(function () use ($plugin) {
        foreach ($plugin->publicRouteDefinitions() as $routes) {
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
      throw new PluginException("Plugin [{$plugin->handle()}] public route file [{$routes}] does not exist.");
    }

    require $routes;
  }
}
