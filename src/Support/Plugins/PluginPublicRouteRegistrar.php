<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\Route;

/**
 * Mounts enabled plugins' own public route files under a reserved public
 * prefix, so a plugin can own a visitor-facing surface without the CMS
 * hardcoding its endpoints.
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
 *
 * Webhook routes are the one exception, mounted under the same prefix with CSRF
 * off — see `PluginDefinition::webhookRoutes()` for why a third-party callback
 * fits neither group. They are declared in a separate file so that exemption
 * covers exactly the routes a plugin meant it to, and so the ones it applies to
 * can be read off the manifest rather than inferred from a global path list.
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

  /**
   * Every class the CSRF check might be registered under.
   *
   * The `web` group is the host application's, and which class it holds has
   * changed across Laravel versions and is often a host-owned subclass. Naming
   * one and excluding it would silently leave the check in place on any install
   * spelling it differently — a webhook rejected with a 419 that nobody sees,
   * because the caller is a payment gateway. Excluding a class the stack does
   * not contain costs nothing.
   *
   * @var array<int, class-string|string>
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

  public function registerEnabledPublicRoutes(): void
  {
    if (app()->routesAreCached()) {
      return;
    }

    foreach ($this->plugins->enabled() as $plugin) {
      if ($plugin->publicRouteDefinitions() === [] && $plugin->webhookRouteDefinitions() === []) {
        continue;
      }

      $this->registerPublicRoutesFor($plugin);
    }

    Route::getRoutes()->refreshNameLookups();
  }

  public function registerPublicRoutesFor(PluginDefinition $plugin): void
  {
    if ($plugin->publicRouteDefinitions() !== []) {
      Route::middleware(self::GROUP_MIDDLEWARE)
        ->prefix(ltrim($plugin->publicRoutePrefix(), '/'))
        ->name($plugin->publicRouteNamePrefix().'.')
        ->group(function () use ($plugin) {
          foreach ($plugin->publicRouteDefinitions() as $routes) {
            $this->registerRouteDefinition($plugin, $routes);
          }
        });
    }

    if ($plugin->webhookRouteDefinitions() !== []) {
      /*
       * Same prefix, same name namespace, same throttle — CSRF removed and
       * nothing else. Dropping it from the group rather than adding the paths to
       * a global exemption list is what keeps the exemption attributable: it
       * covers the routes in this file and cannot quietly widen to a path that
       * merely looks similar.
       */
      Route::middleware(self::GROUP_MIDDLEWARE)
        ->withoutMiddleware(self::CSRF_MIDDLEWARE)
        ->prefix(ltrim($plugin->publicRoutePrefix(), '/'))
        ->name($plugin->publicRouteNamePrefix().'.')
        ->group(function () use ($plugin) {
          foreach ($plugin->webhookRouteDefinitions() as $routes) {
            $this->registerRouteDefinition($plugin, $routes);
          }
        });
    }

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
