<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRouteRegistrar;

class PluginRouteFallbackController extends Controller
{
  public function __construct(
    private readonly PluginRegistry $plugins,
    private readonly PluginRouteRegistrar $routes,
  ) {}

  public function __invoke(Request $request, string $plugin, ?string $pluginPath = null): Response
  {
    $definition = $this->plugins->get($plugin);

    abort_if($definition === null || ! $this->plugins->isEnabled($plugin), 404);

    $this->routes->registerAdminRoutesFor($definition);

    $path = trim((string) $pluginPath, '/');

    if ($plugin === 'webblocks-ui-manager') {
      return $this->webBlocksUiManager($request, $path);
    }

    abort(404);
  }

  private function webBlocksUiManager(Request $request, string $path): Response
  {
    if ($request->isMethod('GET') && $path === 'releases') {
      abort_unless($request->user()?->can('webblocks-ui-manager.view'), 403);

      $controller = 'WebBlocks\\Cms\\Plugins\\WebBlocksUiManager\\Http\\Controllers\\WebBlocksUiReleaseController';

      abort_unless(class_exists($controller), 404);

      return app($controller)->index();
    }

    if ($request->isMethod('GET') && $path === 'settings') {
      abort_unless($request->user()?->can('webblocks-ui-manager.manage'), 403);

      return app(PluginSettingsController::class)->edit($request);
    }

    abort(404);
  }
}
