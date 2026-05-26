<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use WebBlocks\Cms\Support\Plugins\PluginAdminExtensionRegistry;
use WebBlocks\Cms\Support\Plugins\PluginHealthMonitor;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRouteRegistrar;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class SystemPluginController extends Controller
{
  public function __construct(
    private readonly PluginRegistry $plugins,
    private readonly PluginHealthMonitor $health,
    private readonly PluginRouteRegistrar $routes,
    private readonly PluginAdminExtensionRegistry $pluginAdminExtensions,
    private readonly SystemSettings $systemSettings,
  ) {}

  public function index(): View
  {
    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.plugins.index', [
      'title' => 'Plugins',
      'adminProjectIdentity' => $this->systemSettings->adminProjectIdentity(),
      'adminBrowserTitle' => $this->systemSettings->adminBrowserTitle('Plugins'),
      'plugins' => $this->pluginSummaries(),
      'pluginSystemCards' => $this->pluginAdminExtensions->systemCards(request()->user()),
    ]);
  }

  public function show(string $plugin): View
  {
    $definition = $this->plugins->get($plugin);

    abort_if($definition === null, 404);

    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.plugins.show', [
      'title' => $definition->labelText(),
      'adminProjectIdentity' => $this->systemSettings->adminProjectIdentity(),
      'adminBrowserTitle' => $this->systemSettings->adminBrowserTitle($definition->labelText()),
      'plugin' => $this->pluginSummary($definition->handle()),
    ]);
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function pluginSummaries(): array
  {
    return array_map(
      fn (array $plugin): array => $this->decorateSummary($plugin),
      $this->plugins->summaries()
    );
  }

  /**
   * @return array<string, mixed>
   */
  private function pluginSummary(string $handle): array
  {
    $summary = collect($this->plugins->summaries())
      ->firstWhere('handle', $handle);

    abort_if($summary === null, 404);

    return $this->decorateSummary($summary);
  }

  /**
   * @param  array<string, mixed>  $summary
   * @return array<string, mixed>
   */
  private function decorateSummary(array $summary): array
  {
    $definition = $this->plugins->get((string) $summary['handle']);

    abort_if($definition === null, 404);

    $settings = $definition->settingsDefinition();
    $settingsRoute = null;

    if ($settings !== null && $summary['enabled']) {
      $settingsRoute = $settings->routeName ?? $this->routes->defaultSettingsRouteName($definition);
    }

    return array_merge($summary, [
      'health' => $this->health->healthArrayFor($definition),
      'settings' => $settings?->toArray(),
      'settings_route' => $settingsRoute,
      'settings_url' => $settingsRoute !== null && Route::has($settingsRoute) ? route($settingsRoute) : null,
    ]);
  }
}
