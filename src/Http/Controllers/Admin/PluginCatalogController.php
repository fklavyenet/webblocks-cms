<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use RuntimeException;
use WebBlocks\Cms\Support\Plugins\Catalog\CatalogPluginInstallBridge;
use WebBlocks\Cms\Support\Plugins\Catalog\PluginCatalogClient;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRuntimeRefresher;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PluginCatalogController extends Controller
{
  public function __construct(
    private readonly PluginCatalogClient $catalog,
    private readonly CatalogPluginInstallBridge $installBridge,
    private readonly PluginRegistry $plugins,
    private readonly SystemSettings $systemSettings,
    private readonly PluginRuntimeRefresher $runtimeRefresher,
  ) {}

  public function index(): View
  {
    $result = $this->catalog->browse();

    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.plugins.catalog.index', [
      'title' => 'Plugin Catalog',
      'adminProjectIdentity' => $this->systemSettings->adminProjectIdentity(),
      'adminBrowserTitle' => $this->systemSettings->adminBrowserTitle('Plugin Catalog'),
      'catalog' => $result,
    ]);
  }

  public function show(string $handle): View
  {
    $result = $this->catalog->show($handle);
    $title = $result->plugin?->label ?? 'Plugin Catalog Detail';

    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.plugins.catalog.show', [
      'title' => $title,
      'adminProjectIdentity' => $this->systemSettings->adminProjectIdentity(),
      'adminBrowserTitle' => $this->systemSettings->adminBrowserTitle($title),
      'catalog' => $result,
      'handle' => $handle,
      'installedState' => $this->installedState($handle),
      'manualUploadUrl' => Route::has('admin.system.plugins.index') ? route('admin.system.plugins.index') : null,
    ]);
  }

  public function install(string $handle): RedirectResponse
  {
    abort_unless(request()->user()?->isSuperAdmin(), 403);

    $result = $this->catalog->show($handle);

    if (! $result->available || $result->plugin === null) {
      return back()->withErrors([
        'catalog_install' => $result->message ?? 'The catalog plugin could not be loaded for installation.',
      ]);
    }

    try {
      $installed = $this->installBridge->install($result->plugin);
    } catch (RuntimeException $exception) {
      return back()->withErrors(['catalog_install' => $this->controlledError($exception)]);
    }

    $this->runtimeRefresher->refresh();

    return redirect()
      ->route('admin.system.plugins.index')
      ->with('status', 'Plugin '.$installed['handle'].' '.$installed['version'].' was installed disabled from the Plugin Catalog. Review it before enabling.');
  }

  /**
   * @return array{installed: bool, version: ?string, enabled: bool}
   */
  private function installedState(string $handle): array
  {
    $summary = collect($this->plugins->summaries())
      ->firstWhere('handle', $handle);

    if (! is_array($summary)) {
      return [
        'installed' => false,
        'version' => null,
        'enabled' => false,
      ];
    }

    return [
      'installed' => true,
      'version' => is_string($summary['version'] ?? null) ? $summary['version'] : null,
      'enabled' => (bool) ($summary['enabled'] ?? false),
    ];
  }

  private function controlledError(RuntimeException $exception): string
  {
    $message = $exception->getMessage();

    return $message !== ''
      ? $message
      : 'The catalog plugin could not be installed. Review the catalog metadata and try again.';
  }
}
