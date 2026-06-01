<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Support\Plugins\Catalog\PluginCatalogClient;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PluginCatalogController extends Controller
{
  public function __construct(
    private readonly PluginCatalogClient $catalog,
    private readonly SystemSettings $systemSettings,
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
    ]);
  }
}
