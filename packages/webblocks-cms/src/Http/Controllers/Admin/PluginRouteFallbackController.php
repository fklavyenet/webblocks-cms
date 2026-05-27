<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRouteRegistrar;

class PluginRouteFallbackController extends Controller
{
  public function __construct(
    private readonly PluginRegistry $plugins,
    private readonly PluginRouteRegistrar $routes,
  ) {}

  public function __invoke(Request $request, ?string $plugin = null, ?string $pluginPath = null): Response|View
  {
    $plugin = $this->routeString($request, 'plugin') ?? $plugin;
    $pluginPath = $this->routeString($request, 'pluginPath') ?? $pluginPath;

    abort_if($plugin === null || $plugin === '', 404);

    $definition = $this->plugins->get($plugin);

    abort_if($definition === null || ! $this->plugins->isEnabled($plugin), 404);

    $this->routes->registerAdminRoutesFor($definition);

    $path = $this->pluginPath($request, $plugin, $pluginPath);

    if ($plugin === 'webblocks-ui-manager') {
      return $this->webBlocksUiManager($request, $path);
    }

    abort(404);
  }

  private function webBlocksUiManager(Request $request, string $path): Response|View
  {
    $controller = 'WebBlocks\\Cms\\Plugins\\WebBlocksUiManager\\Http\\Controllers\\WebBlocksUiReleaseController';
    abort_unless(class_exists($controller), 404);

    $routeName = (string) $request->route()?->getName();

    if ($request->isMethod('GET') && $this->matches($routeName, $path, 'releases.index', 'releases')) {
      abort_unless($request->user()?->can('webblocks-ui-manager.view'), 403);

      return app($controller)->index();
    }

    if ($request->isMethod('GET') && $this->matches($routeName, $path, 'releases.create', 'releases/create')) {
      abort_unless($request->user()?->can('webblocks-ui-manager.manage'), 403);

      if ($this->webBlocksUiManagerReleaseTablesMissing()) {
        return app($controller)->index();
      }

      return app($controller)->create();
    }

    if ($request->isMethod('POST') && $this->matches($routeName, $path, 'releases.store', 'releases')) {
      abort_unless($request->user()?->can('webblocks-ui-manager.manage'), 403);

      if ($this->webBlocksUiManagerReleaseTablesMissing()) {
        return $this->releaseTablesMissingRedirect();
      }

      return app()->call([app($controller), 'store']);
    }

    $release = $this->releaseRouteValue($request, $path);

    if ($request->isMethod('GET') && $release !== null && $this->matches($routeName, $path, 'releases.show', 'releases/'.$release)) {
      abort_unless($request->user()?->can('webblocks-ui-manager.view'), 403);

      if ($this->webBlocksUiManagerReleaseTablesMissing()) {
        return app($controller)->index();
      }

      return app($controller)->show($release);
    }

    if ($request->isMethod('GET') && $release !== null && $this->matches($routeName, $path, 'releases.edit', 'releases/'.$release.'/edit')) {
      abort_unless($request->user()?->can('webblocks-ui-manager.manage'), 403);

      if ($this->webBlocksUiManagerReleaseTablesMissing()) {
        return app($controller)->index();
      }

      return app($controller)->edit($release);
    }

    if ($request->isMethod('PUT') && $release !== null && $this->matches($routeName, $path, 'releases.update', 'releases/'.$release)) {
      abort_unless($request->user()?->can('webblocks-ui-manager.manage'), 403);

      if ($this->webBlocksUiManagerReleaseTablesMissing()) {
        return $this->releaseTablesMissingRedirect();
      }

      return app()->call([app($controller), 'update'], ['release' => $release]);
    }

    if ($request->isMethod('POST') && $release !== null && $this->matches($routeName, $path, 'releases.publish.dry-run', 'releases/'.$release.'/publish-dry-run')) {
      abort_unless($request->user()?->can('webblocks-ui-manager.publish'), 403);

      if ($this->webBlocksUiManagerReleaseTablesMissing()) {
        return $this->releaseTablesMissingRedirect();
      }

      return app($controller)->dryRun($release);
    }

    if ($request->isMethod('POST') && $release !== null && $this->matches($routeName, $path, 'releases.publish', 'releases/'.$release.'/publish')) {
      abort_unless($request->user()?->can('webblocks-ui-manager.publish'), 403);

      if ($this->webBlocksUiManagerReleaseTablesMissing()) {
        return $this->releaseTablesMissingRedirect();
      }

      return app($controller)->publish($release);
    }

    if ($request->isMethod('GET') && $this->matches($routeName, $path, 'settings.edit', 'settings')) {
      abort_unless($request->user()?->can('webblocks-ui-manager.manage'), 403);

      return app(PluginSettingsController::class)->edit($request);
    }

    abort(404);
  }

  private function matches(string $routeName, string $path, string $routeSuffix, string $expectedPath): bool
  {
    return str_ends_with($routeName, '.'.$routeSuffix) || $path === $expectedPath;
  }

  private function webBlocksUiManagerReleaseTablesMissing(): bool
  {
    $schema = 'WebBlocks\\Cms\\Plugins\\WebBlocksUiManager\\Support\\WebBlocksUiManagerSchema';

    return class_exists($schema) && ! app($schema)->isReady();
  }

  private function releaseTablesMissingRedirect(): Response
  {
    $schema = 'WebBlocks\\Cms\\Plugins\\WebBlocksUiManager\\Support\\WebBlocksUiManagerSchema';
    $message = class_exists($schema)
      ? app($schema)->message()
      : 'Setup required. Plugin migrations pending. Release tables are missing.';

    return redirect()
      ->route('webblocks.plugins.webblocks_ui_manager.releases.index')
      ->withErrors(['plugin' => $message]);
  }

  private function pluginPath(Request $request, string $plugin, ?string $pluginPath): string
  {
    $prefix = 'webadmin/plugins/'.$plugin;
    $requestPath = trim($request->path(), '/');

    if ($requestPath === $prefix) {
      return '';
    }

    if (str_starts_with($requestPath, $prefix.'/')) {
      return trim(substr($requestPath, strlen($prefix) + 1), '/');
    }

    return trim((string) $pluginPath, '/');
  }

  private function routeString(Request $request, string $key): ?string
  {
    $value = $request->route($key);

    if (is_string($value) || is_numeric($value)) {
      return (string) $value;
    }

    return null;
  }

  private function releaseRouteValue(Request $request, string $path): ?string
  {
    $routeValue = $this->routeString($request, 'release');

    if ($routeValue !== null) {
      return $routeValue;
    }

    if (preg_match('#^releases/([^/]+)(?:/edit|/publish|/publish-dry-run)?$#', $path, $matches) === 1) {
      return $matches[1];
    }

    return null;
  }
}
