<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class SystemPluginController extends Controller
{
  public function __construct(
    private readonly PluginRegistry $plugins,
    private readonly SystemSettings $systemSettings,
  ) {}

  public function index(): View
  {
    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.plugins.index', [
      'title' => 'Plugins',
      'adminProjectIdentity' => $this->systemSettings->adminProjectIdentity(),
      'adminBrowserTitle' => $this->systemSettings->adminBrowserTitle('Plugins'),
      'plugins' => $this->plugins->summaries(),
    ]);
  }
}
