<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use RuntimeException;
use Throwable;
use WebBlocks\Cms\Actions\Plugins\EnablePlugin;
use WebBlocks\Cms\Actions\Plugins\UpdatePluginAppearance;
use WebBlocks\Cms\Http\Requests\Admin\PluginAppearanceRequest;
use WebBlocks\Cms\Support\Extensions\ExtensionOperation;
use WebBlocks\Cms\Support\Plugins\Catalog\CatalogPlugin;
use WebBlocks\Cms\Support\Plugins\Catalog\CatalogPluginInstallBridge;
use WebBlocks\Cms\Support\Plugins\Catalog\PluginCatalogClient;
use WebBlocks\Cms\Support\Plugins\Catalog\PluginCatalogUpdates;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Plugins\PluginAppearance;
use WebBlocks\Cms\Support\Plugins\PluginCompatibility;
use WebBlocks\Cms\Support\Plugins\PluginDatabaseSetup;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginHealthMonitor;
use WebBlocks\Cms\Support\Plugins\PluginMigrationRunner;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRouteRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginRuntimeRefresher;
use WebBlocks\Cms\Support\Plugins\PluginZipInstaller;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
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
    private readonly PluginCatalogUpdates $catalogUpdates,
    private readonly CatalogPluginInstallBridge $catalogInstallBridge,
    private readonly PluginRuntimeRefresher $runtimeRefresher,
  ) {}

  public function index(): View
  {
    $canInstallPlugins = (bool) request()->user()?->isSuperAdmin();
    $updateStatus = $canInstallPlugins ? $this->catalogUpdates->refreshIfDue() : null;

    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.plugins.index', [
      'title' => 'Plugins',
      'adminProjectIdentity' => $this->systemSettings->adminProjectIdentity(),
      'adminBrowserTitle' => $this->systemSettings->adminBrowserTitle('Plugins'),
      'plugins' => $this->pluginSummaries($updateStatus['updates'] ?? []),
      'canInstallPlugins' => $canInstallPlugins,
      'pluginCatalogUpdateStatus' => $updateStatus,
    ]);
  }

  public function updateIndicator(Request $request, AdminLocaleResolver $locales): JsonResponse
  {
    abort_unless($request->user()?->isSuperAdmin(), 403);

    $count = count($this->catalogUpdates->refreshIfDue()['updates']);

    return response()->json([
      'count' => $count,
      'label' => trans_choice('webblocks-cms::notifications.panel_plugin_updates', $count, [], $locales->locale()),
    ])->header('Cache-Control', 'no-store');
  }

  public function checkCatalogUpdates(Request $request): RedirectResponse
  {
    abort_unless($request->user()?->isSuperAdmin(), 403);

    return redirect()->route('admin.system.plugins.index')
      ->withFragment('registered-plugins')
      ->with('plugin_catalog_check_result', $this->catalogUpdates->checkNow());
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

    $operation = new ExtensionOperation('plugin', 'install');
    $operation->validate(fn () => $request->validate([
      'plugin_zip' => ['required', 'file', 'mimes:zip', 'max:20480'],
    ], [
      'plugin_zip.required' => __('webblocks-cms::admin-feedback.panel_copy.plugin_zip_required'),
      'plugin_zip.mimes' => __('webblocks-cms::admin-feedback.panel_validation.plugin_zip_type'),
      'plugin_zip.max' => __('webblocks-cms::admin-feedback.panel_validation.plugin_zip_size'),
    ]), 'plugin_zip');

    try {
      $installed = $this->installer->install($request->file('plugin_zip')->getRealPath(), $operation);
      $operation->during('runtime');
      $this->runtimeRefresher->refresh();
    } catch (Throwable) {
      return back()->withErrors(['plugin_zip' => $operation->fail()])->withInput();
    }

    return redirect()
      ->route('admin.system.plugins.index')
      ->with('status', __('webblocks-cms::admin-feedback.plugins.installed', ['handle' => $installed['handle'], 'version' => $installed['version']]));
  }

  public function enable(string $plugin): RedirectResponse
  {
    abort_unless(request()->user()?->isSuperAdmin(), 403);

    $definition = $this->plugins->get($plugin);

    abort_if($definition === null || $definition->installPathValue() === null, 404);

    $operation = ExtensionOperation::plugin('enable', $definition, 'package');
    if (! is_dir($definition->installPathValue())) {
      return back()->withErrors(['plugin' => $operation->fail()]);
    }

    if ($error = $this->cmsCompatibilityError($definition, $operation)) {
      return $error;
    }

    $version = $definition->versionText();

    abort_if($version === null, 422);

    try {
      app(EnablePlugin::class)->execute($definition, $version, repairRecordedMigrations: $this->setupRequired($definition), operation: $operation);
      $operation->during('runtime');
      $this->runtimeRefresher->refresh();
    } catch (Throwable) {
      return back()->withErrors(['plugin' => $operation->fail()]);
    }

    return redirect()
      ->route('admin.system.plugins.show', $plugin)
      ->with('status', __('webblocks-cms::admin-feedback.plugins.enabled'));
  }

  public function disable(string $plugin): RedirectResponse
  {
    abort_unless(request()->user()?->isSuperAdmin(), 403);

    $definition = $this->plugins->get($plugin);

    abort_if($definition === null || $definition->installPathValue() === null, 404);

    try {
      $this->installedPlugins->disable($plugin);
    } catch (RuntimeException $exception) {
      return back()->withErrors(['plugin' => $exception->getMessage()]);
    }
    $this->runtimeRefresher->refresh();

    return redirect()
      ->route('admin.system.plugins.show', $plugin)
      ->with('status', __('webblocks-cms::admin-feedback.plugins.disabled'));
  }

  public function updateAppearance(PluginAppearanceRequest $request, string $plugin, UpdatePluginAppearance $action, PluginAppearance $appearance): RedirectResponse
  {
    $definition = $this->plugins->get($plugin);
    abort_if($definition === null, 404);
    $data = $request->validated();
    $action->execute($definition, $data['sidebar_icon'] ?? null);
    $routeName = $appearance->settingsRouteName($definition);
    $destination = $data['origin'] === 'settings' && Route::has($routeName)
      ? route($routeName, isset($data['site_id']) ? ['site_id' => $data['site_id']] : [])
      : route('admin.system.plugins.show', $plugin);

    return redirect($destination.'#plugin-menu-appearance')
      ->with('plugin_appearance_status', __('webblocks-cms::admin.plugin_appearance.saved'));
  }

  public function setup(string $plugin): RedirectResponse
  {
    abort_unless(request()->user()?->isSuperAdmin(), 403);

    $definition = $this->plugins->get($plugin);

    abort_if($definition === null || $definition->installPathValue() === null, 404);

    $operation = ExtensionOperation::plugin('setup', $definition, 'activation');
    if ($error = $this->cmsCompatibilityError($definition, $operation)) {
      return $error;
    }

    if (! $this->plugins->isConfiguredEnabled($plugin)) {
      return back()->withErrors(['plugin' => __('webblocks-cms::admin-feedback.plugins.enable_before_setup').' '.$operation->fail()]);
    }

    try {
      app(PluginDatabaseSetup::class)->run($definition, repairRecordedMigrations: $this->setupRequired($definition), operation: $operation);
      $operation->during('runtime');
      $this->runtimeRefresher->refresh();
    } catch (Throwable) {
      return back()->withErrors(['plugin' => $operation->fail()]);
    }

    return redirect()
      ->route('admin.system.plugins.show', $plugin)
      ->with('status', __('webblocks-cms::admin.system_plugins_show.database_update_completed'));
  }

  public function uninstall(string $plugin): RedirectResponse
  {
    abort_unless(request()->user()?->isSuperAdmin(), 403);

    $definition = $this->plugins->get($plugin);

    abort_unless($definition?->isManuallyInstalled(), 404);

    if ($this->plugins->isConfiguredEnabled($plugin)) {
      return back()->withErrors(['plugin' => __('webblocks-cms::admin-feedback.plugins.disable_before_uninstall')]);
    }

    $version = $definition->versionText();

    abort_if($version === null, 422);

    try {
      $this->installedPlugins->uninstall($plugin, $version, requireDisabled: true);
    } catch (RuntimeException $exception) {
      return back()->withErrors(['plugin' => $exception->getMessage()]);
    }

    $this->runtimeRefresher->refresh();

    return redirect()
      ->route('admin.system.plugins.index')
      ->with('status', __('webblocks-cms::admin-feedback.plugins.uninstalled'));
  }

  public function updateFromCatalog(string $plugin): RedirectResponse
  {
    abort_unless(request()->user()?->isSuperAdmin(), 403);

    $definition = $this->plugins->get($plugin);

    abort_if($definition === null || $definition->installPathValue() === null, 404);

    $installedVersion = $definition->versionText();

    abort_if($installedVersion === null, 422);

    $operation = new ExtensionOperation('plugin', 'update', 'download');
    $operation->identify($definition->handle(), label: $definition->labelText());
    try {
      $result = $this->catalog->show($plugin);
      if (! $result->available || $result->plugin === null) {
        return back()->withErrors(['plugin' => $operation->fail()]);
      }

      $operation->identify($definition->handle(), $result->plugin->latestCompatibleRelease?->version);
      $operation->during('compatibility');
      if (! $this->catalogPluginIsUpdateFor($result->plugin, $installedVersion)) {
        return back()->withErrors(['plugin' => $operation->fail()]);
      }

      $installed = $this->catalogInstallBridge->update($result->plugin, $installedVersion, $operation);
      $operation->during('runtime');
      $this->runtimeRefresher->refreshInstalledPackageAssets(
        (string) $installed['handle'],
        (string) $installed['version'],
        (string) $installed['path'],
      );
      $this->runtimeRefresher->clearCompiledViews();
    } catch (Throwable) {
      return back()->withErrors(['plugin' => $operation->fail()]);
    }

    /*
     * Every class from the installed version may already be loaded in this PHP
     * request, including manifest readers, health reporters and asset
     * definitions. Rebuilding the runtime registries here could therefore mix
     * old in-memory classes with the new package on disk. Static assets are safe
     * to publish directly from the install result without loading provider code,
     * and compiled Blade files persist across requests, so both are refreshed
     * explicitly. The redirected request then performs normal plugin discovery,
     * route registration and health checks from one consistent version.
     */

    return redirect()
      ->route('admin.system.plugins.index')
      ->with('status', __('webblocks-cms::admin-feedback.plugins.updated_catalog', ['plugin' => $definition->labelText(), 'version' => $installed['version']]));
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function cmsCompatibilityError(PluginDefinition $plugin, ExtensionOperation $operation): ?RedirectResponse
  {
    try {
      app(PluginCompatibility::class)->assertCompatible($plugin);
    } catch (RuntimeException) {
      $operation->during('compatibility');

      $requirement = app(PluginCompatibility::class)->diagnosticRequirement($plugin);

      return back()->withErrors(['plugin' => trim($operation->fail().' '.$requirement)]);
    }

    return null;
  }

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

    $manual = $definition->isManuallyInstalled();
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
        'checks' => [],
      ];
    }

    $setupRequired = $enabled && ($health['status'] ?? null) === 'warning' && str_contains((string) ($health['message'] ?? ''), 'Setup required');
    $hasMigrations = count($definition->migrationPaths()) > 0;
    $migrationsPending = false;
    $databaseSetup = $manual && $definition->versionText() !== null
      ? $this->installedPlugins->setupResult($definition->handle(), $definition->versionText())
      : [];

    if ($manual && $enabled && $compatible && $filesAvailable && $hasMigrations) {
      try {
        $migrationsPending = $setupRequired || $this->migrationRunner->hasPendingMigrations($definition);
      } catch (RuntimeException) {
        // Keep setup actionable so its normal error path can explain invalid migration declarations.
        $migrationsPending = true;
      }
    }
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
      'can_disable' => $manual && (bool) $summary['configured_enabled'],
      'can_uninstall' => $manual,
      'can_setup' => $manual && $enabled && $compatible && $filesAvailable && $hasMigrations,
      'migrations_pending' => $migrationsPending,
      'database_setup_failed' => ($databaseSetup['status'] ?? null) === 'failed',
      'setup_required' => $setupRequired,
      'settings' => $settings?->toArray(),
      'settings_route' => $settingsRoute,
      'settings_url' => $settingsRoute !== null ? $this->settingsUrl($definition, $settingsRoute) : null,
      'catalog_update' => $catalogUpdate,
      'can_update_from_catalog' => $catalogUpdate !== null,
    ]);
  }

  private function catalogPluginIsUpdateFor(CatalogPlugin $plugin, string $installedVersion): bool
  {
    $release = $plugin->latestCompatibleRelease;

    return $plugin->hasInstallableArtifact()
      && $release?->version !== null
      && version_compare($release->version, $installedVersion, '>');
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
