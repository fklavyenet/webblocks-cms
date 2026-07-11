<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\Admin\PluginRouteFallbackController;
use WebBlocks\Cms\Http\Controllers\Admin\PluginSettingsController;
use WebBlocks\Cms\Http\Middleware\GuardPluginSetup;
use WebBlocks\Cms\Http\Middleware\ServeCmsPageBeforeRedirectCatchAll;
use WebBlocks\Cms\Http\Middleware\UseCmsAuthenticationRedirect;
use WebBlocks\Cms\Models\Locale;

class PluginRouteRegistrar
{
  /**
   * @var array<int, string>
   */
  private const ADMIN_MIDDLEWARE = [
    'web',
    'install.required',
    UseCmsAuthenticationRedirect::class,
    'admin.access',
  ];

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

    $this->protectCorePublicRoutesFromPluginCatchAlls();
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

    Route::middleware(self::ADMIN_MIDDLEWARE)
      ->middleware(GuardPluginSetup::class.':'.$plugin->handle())
      ->prefix(ltrim($plugin->adminRoutePrefix(), '/'))
      ->name($plugin->routeNamePrefix().'.')
      ->group(function () use ($plugin) {
        $this->registerDefaultSettingsRoute($plugin);

        foreach ($plugin->adminRouteDefinitions() as $routes) {
          $this->registerRouteDefinition($plugin, $routes);
        }
      });

    $this->normalizePluginAdminRouteMiddleware($plugin);
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
      ...self::ADMIN_MIDDLEWARE,
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
      ->middleware(self::ADMIN_MIDDLEWARE)
      ->name('webblocks.plugins.fallback');
  }

  public function protectCorePublicRoutesFromPluginCatchAlls(): void
  {
    $redirectManagerRoute = Route::getRoutes()->getByName('webblocks.redirect-manager.public');

    if ($redirectManagerRoute === null || $redirectManagerRoute->uri() !== '{webblocksRedirectManagerPath}') {
      return;
    }

    $reservedPrefixes = [
      'webadmin',
      'cms',
      'commerce',
      'storage',
      'assets',
      'static',
      'build',
      'vendor',
      'webblocks-ui',
      'p',
      'search',
      'search\.json',
      Locale::routePattern(),
    ];

    // Reserve the first segment of every real registered route (for example
    // login, register, forgot-password, install, up) so the redirect-manager
    // catch-all never hijacks a genuine route and returns a 404 for it. Public
    // CMS pages are served by dynamic `{slug}` routes, which are skipped here,
    // so redirect handling for non-route paths keeps working.
    foreach (Route::getRoutes()->getRoutes() as $registeredRoute) {
      if ($registeredRoute->getName() === 'webblocks.redirect-manager.public') {
        continue;
      }

      $firstSegment = explode('/', ltrim($registeredRoute->uri(), '/'))[0];

      if ($firstSegment === '' || str_starts_with($firstSegment, '{')) {
        continue;
      }

      $reservedPrefixes[] = preg_quote($firstSegment, '/');
    }

    $reservedPrefixes = array_values(array_unique($reservedPrefixes));

    $redirectManagerRoute->where(
      'webblocksRedirectManagerPath',
      '^(?!(?:'.implode('|', $reservedPrefixes).')(?:/|$)).+',
    );
    $redirectManagerRoute->middleware(ServeCmsPageBeforeRedirectCatchAll::class);
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

  private function normalizePluginAdminRouteMiddleware(PluginDefinition $plugin): void
  {
    $uriPrefix = trim($plugin->adminRoutePrefix(), '/');
    $namePrefix = $plugin->routeNamePrefix().'.';
    $setupGuard = GuardPluginSetup::class.':'.$plugin->handle();

    foreach (Route::getRoutes()->getRoutes() as $route) {
      $routeName = $route->getName();

      if (! is_string($routeName)
        || ! str_starts_with($routeName, $namePrefix)
        || ! $this->routeUriBelongsToPlugin($route->uri(), $uriPrefix)) {
        continue;
      }

      $action = $route->getAction();
      $existing = $this->routeMiddleware($action['middleware'] ?? []);
      $pluginSpecific = array_values(array_filter(
        $existing,
        fn (string $middleware): bool => ! $this->isPluginAdminStackMiddleware($middleware, $plugin->handle())
      ));

      $action['middleware'] = array_values(array_unique([
        ...self::ADMIN_MIDDLEWARE,
        $setupGuard,
        ...$pluginSpecific,
      ]));

      $route->setAction($action);
    }
  }

  /**
   * @return array<int, string>
   */
  private function routeMiddleware(mixed $middleware): array
  {
    if (is_string($middleware)) {
      return [$middleware];
    }

    if (! is_array($middleware)) {
      return [];
    }

    return array_values(array_filter($middleware, fn (mixed $item): bool => is_string($item) && $item !== ''));
  }

  private function isPluginAdminStackMiddleware(string $middleware, string $plugin): bool
  {
    return in_array($middleware, self::ADMIN_MIDDLEWARE, true)
      || $middleware === GuardPluginSetup::class.':'.$plugin;
  }

  private function routeUriBelongsToPlugin(string $uri, string $uriPrefix): bool
  {
    return $uri === $uriPrefix || str_starts_with($uri, $uriPrefix.'/');
  }
}
