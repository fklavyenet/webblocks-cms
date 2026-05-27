<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use RuntimeException;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Plugins\PluginAdminExtensionRegistry;
use WebBlocks\Cms\Support\Plugins\PluginHealthMonitor;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRouteRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginZipInstaller;
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
    private readonly PluginZipInstaller $installer,
    private readonly InstalledPluginRepository $installedPlugins,
  ) {}

  public function index(): View
  {
    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.plugins.index', [
      'title' => 'Plugins',
      'adminProjectIdentity' => $this->systemSettings->adminProjectIdentity(),
      'adminBrowserTitle' => $this->systemSettings->adminBrowserTitle('Plugins'),
      'plugins' => $this->pluginSummaries(),
      'pluginSystemCards' => $this->pluginAdminExtensions->systemCards(request()->user()),
      'canInstallPlugins' => (bool) request()->user()?->isSuperAdmin(),
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

  public function upload(Request $request): RedirectResponse
  {
    abort_unless($request->user()?->isSuperAdmin(), 403);

    $request->validate([
      'plugin_zip' => ['required', 'file', 'mimes:zip', 'max:20480'],
    ]);

    try {
      $installed = $this->installer->install($request->file('plugin_zip')->getRealPath());
    } catch (RuntimeException $exception) {
      return back()
        ->withErrors(['plugin_zip' => $exception->getMessage()])
        ->withInput();
    }

    app()->forgetInstance(PluginRegistry::class);

    return redirect()
      ->route('admin.system.plugins.index')
      ->with('status', 'Plugin '.$installed['handle'].' '.$installed['version'].' was installed disabled. Review it before enabling.');
  }

  public function enable(string $plugin): RedirectResponse
  {
    abort_unless(request()->user()?->isSuperAdmin(), 403);

    $definition = $this->plugins->get($plugin);

    abort_if($definition === null || $definition->installPathValue() === null, 404);

    if (! $this->plugins->isCompatible($plugin)) {
      return back()->withErrors(['plugin' => $this->plugins->incompatibilityMessage($plugin) ?? 'Plugin is not compatible with this CMS version.']);
    }

    $version = $definition->versionText();

    abort_if($version === null, 422);

    $this->installedPlugins->enable($plugin, $version);
    app()->forgetInstance(PluginRegistry::class);

    return redirect()
      ->route('admin.system.plugins.show', $plugin)
      ->with('status', 'Plugin enabled. Its routes, commands, permissions, and menus will be active on the next request.');
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
