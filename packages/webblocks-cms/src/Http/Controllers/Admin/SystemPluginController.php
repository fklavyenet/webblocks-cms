<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use RuntimeException;
use Throwable;
use WebBlocks\Cms\Support\Plugins\Catalog\CatalogPlugin;
use WebBlocks\Cms\Support\Plugins\Catalog\CatalogPluginInstallBridge;
use WebBlocks\Cms\Support\Plugins\Catalog\PluginCatalogClient;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginHealthMonitor;
use WebBlocks\Cms\Support\Plugins\PluginMigrationRunner;
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
    private readonly SystemSettings $systemSettings,
    private readonly PluginZipInstaller $installer,
    private readonly InstalledPluginRepository $installedPlugins,
    private readonly PluginMigrationRunner $migrationRunner,
    private readonly PluginCatalogClient $catalog,
    private readonly CatalogPluginInstallBridge $catalogInstallBridge,
  ) {}

  public function index(): View
  {
    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.plugins.index', [
      'title' => 'Plugins',
      'adminProjectIdentity' => $this->systemSettings->adminProjectIdentity(),
      'adminBrowserTitle' => $this->systemSettings->adminBrowserTitle('Plugins'),
      'plugins' => $this->pluginSummaries($this->catalogUpdatesByHandle()),
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

    if (! is_dir($definition->installPathValue())) {
      return back()->withErrors(['plugin' => 'Plugin files are missing. Reinstall the plugin package before enabling it.']);
    }

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

  public function disable(string $plugin): RedirectResponse
  {
    abort_unless(request()->user()?->isSuperAdmin(), 403);

    $definition = $this->plugins->get($plugin);

    abort_if($definition === null || $definition->installPathValue() === null, 404);

    $this->installedPlugins->disable($plugin);
    app()->forgetInstance(PluginRegistry::class);

    return redirect()
      ->route('admin.system.plugins.show', $plugin)
      ->with('status', 'Plugin disabled. Routes, commands, menus, settings, health checks, and contributions are inactive.');
  }

  public function setup(string $plugin): RedirectResponse
  {
    abort_unless(request()->user()?->isSuperAdmin(), 403);

    $definition = $this->plugins->get($plugin);

    abort_if($definition === null || $definition->installPathValue() === null, 404);

    if (! $this->plugins->isConfiguredEnabled($plugin)) {
      return back()->withErrors(['plugin' => 'Enable this plugin before running setup.']);
    }

    $version = $definition->versionText();

    abort_if($version === null, 422);

    try {
      $result = $this->migrationRunner->run($definition, repairRecordedMigrations: $this->setupRequired($definition));
      $this->installedPlugins->recordSetupResult($plugin, $version, [
        'status' => 'completed',
        'message' => $result['message'],
        'paths_count' => count($result['paths']),
      ]);
    } catch (RuntimeException $exception) {
      $this->installedPlugins->recordSetupResult($plugin, $version, [
        'status' => 'failed',
        'message' => $exception->getMessage(),
      ]);

      return back()->withErrors(['plugin' => $exception->getMessage()]);
    }

    app()->forgetInstance(PluginRegistry::class);

    return redirect()
      ->route('admin.system.plugins.show', $plugin)
      ->with('status', $result['message']);
  }

  public function uninstall(string $plugin): RedirectResponse
  {
    abort_unless(request()->user()?->isSuperAdmin(), 403);

    $definition = $this->plugins->get($plugin);

    abort_if($definition === null || $definition->installPathValue() === null || $definition->sourceText() !== 'manual upload', 404);

    if ($this->plugins->isConfiguredEnabled($plugin)) {
      return back()->withErrors(['plugin' => 'Disable this plugin before uninstalling it. Plugin-owned database tables are preserved.']);
    }

    $version = $definition->versionText();

    abort_if($version === null, 422);

    try {
      $this->installedPlugins->uninstall($plugin, $version);
    } catch (RuntimeException $exception) {
      return back()->withErrors(['plugin' => $exception->getMessage()]);
    }

    app()->forgetInstance(PluginRegistry::class);

    return redirect()
      ->route('admin.system.plugins.index')
      ->with('status', 'Plugin uninstalled. Plugin-owned database tables were preserved.');
  }

  public function updateFromCatalog(string $plugin): RedirectResponse
  {
    abort_unless(request()->user()?->isSuperAdmin(), 403);

    $definition = $this->plugins->get($plugin);

    abort_if($definition === null || $definition->installPathValue() === null, 404);

    $installedVersion = $definition->versionText();

    abort_if($installedVersion === null, 422);

    $result = $this->catalog->show($plugin);

    if (! $result->available || $result->plugin === null) {
      return back()->withErrors(['plugin' => 'No catalog update is available right now.']);
    }

    if (! $this->catalogPluginIsUpdateFor($result->plugin, $installedVersion)) {
      return back()->withErrors(['plugin' => 'No compatible catalog update is available for this plugin.']);
    }

    try {
      $installed = $this->catalogInstallBridge->update($result->plugin, $installedVersion);
    } catch (RuntimeException $exception) {
      return back()->withErrors(['plugin' => $this->controlledCatalogUpdateError($exception)]);
    }

    app()->forgetInstance(PluginRegistry::class);

    return redirect()
      ->route('admin.system.plugins.index')
      ->with('status', $definition->labelText().' updated to '.$installed['version'].' from Plugin Catalog.');
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function pluginSummaries(array $catalogUpdatesByHandle = []): array
  {
    return array_map(
      fn (array $plugin): array => $this->decorateSummary($plugin, $catalogUpdatesByHandle),
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
  private function decorateSummary(array $summary, array $catalogUpdatesByHandle = []): array
  {
    $definition = $this->plugins->get((string) $summary['handle']);

    abort_if($definition === null, 404);

    $settings = $definition->settingsDefinition();
    $settingsRoute = null;

    if ($settings !== null && $summary['enabled']) {
      $settingsRoute = $settings->routeName ?? $this->routes->defaultSettingsRouteName($definition);
    }

    $manual = $definition->sourceText() === 'manual upload' && $definition->installPathValue() !== null;
    $filesAvailable = $definition->installPathValue() === null || is_dir($definition->installPathValue());
    $compatible = (bool) $summary['compatible'];
    $enabled = (bool) $summary['enabled'];
    $lifecycleStatus = (string) $summary['lifecycle_status'];
    $health = $this->health->healthArrayFor($definition);
    $catalogUpdate = $catalogUpdatesByHandle[$definition->handle()] ?? null;

    if (! $enabled && $compatible && $filesAvailable) {
      $health = [
        'status' => 'inactive',
        'message' => 'Not checked while disabled.',
      ];
    }

    $setupRequired = $enabled && ($health['status'] ?? null) === 'warning' && str_contains((string) ($health['message'] ?? ''), 'Setup required');
    $lifecycleLabel = match (true) {
      ! $filesAvailable => 'Missing files',
      ! $compatible => 'Incompatible',
      $setupRequired => 'Setup required',
      $enabled => 'Enabled',
      $manual => 'Disabled',
      default => ucfirst($lifecycleStatus),
    };

    return array_merge($summary, [
      'health' => $health,
      'lifecycle_label' => $lifecycleLabel,
      'files_available' => $filesAvailable,
      'manual_upload' => $manual,
      'can_enable' => $manual && ! $enabled && $compatible && $filesAvailable,
      'can_disable' => $manual && $enabled,
      'can_uninstall' => $manual,
      'can_setup' => $manual && $enabled && $compatible && $filesAvailable && count($definition->migrationPaths()) > 0,
      'setup_required' => $setupRequired,
      'settings' => $settings?->toArray(),
      'settings_route' => $settingsRoute,
      'settings_url' => $settingsRoute !== null ? $this->settingsUrl($definition, $settingsRoute) : null,
      'catalog_update' => $catalogUpdate,
      'can_update_from_catalog' => $catalogUpdate !== null,
    ]);
  }

  /**
   * @return array<string, array{version: string}>
   */
  private function catalogUpdatesByHandle(): array
  {
    if (! request()->user()?->isSuperAdmin()) {
      return [];
    }

    try {
      $catalog = $this->catalog->browse();
    } catch (Throwable) {
      return [];
    }

    if (! $catalog->available) {
      return [];
    }

    $installedVersions = collect($this->plugins->summaries())
      ->mapWithKeys(fn (array $plugin): array => [
        (string) $plugin['handle'] => is_string($plugin['version'] ?? null) ? $plugin['version'] : null,
      ]);
    $updates = [];

    foreach ($catalog->plugins as $plugin) {
      $installedVersion = $installedVersions->get($plugin->handle);

      if (! is_string($installedVersion) || ! $this->catalogPluginIsUpdateFor($plugin, $installedVersion)) {
        continue;
      }

      $updates[$plugin->handle] = [
        'version' => $plugin->latestCompatibleRelease->version,
      ];
    }

    return $updates;
  }

  private function catalogPluginIsUpdateFor(CatalogPlugin $plugin, string $installedVersion): bool
  {
    $release = $plugin->latestCompatibleRelease;

    return $plugin->hasInstallableArtifact()
      && $release?->version !== null
      && version_compare($release->version, $installedVersion, '>');
  }

  private function controlledCatalogUpdateError(RuntimeException $exception): string
  {
    $message = $exception->getMessage();

    return $message !== ''
      ? $message
      : 'The catalog plugin update could not be installed. Review the catalog metadata and try again.';
  }

  private function settingsUrl(PluginDefinition $definition, string $settingsRoute): string
  {
    if (Route::has($settingsRoute)) {
      return route($settingsRoute);
    }

    return '/'.trim($definition->adminRoutePrefix(), '/').'/settings';
  }

  private function setupRequired(PluginDefinition $definition): bool
  {
    $health = $this->health->healthArrayFor($definition);

    return ($health['status'] ?? null) === 'warning' && str_contains((string) ($health['message'] ?? ''), 'Setup required');
  }
}
