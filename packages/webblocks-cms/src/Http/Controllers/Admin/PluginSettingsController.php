<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PluginSettingsController extends Controller
{
  public function __construct(
    private readonly PluginRegistry $plugins,
    private readonly SystemSettings $systemSettings,
  ) {}

  public function edit(Request $request): View
  {
    $handle = (string) $request->route('plugin');
    $plugin = $this->plugins->get($handle);

    abort_if($plugin === null || ! $this->plugins->isEnabled($handle), 404);
    abort_if($plugin->settingsDefinition() === null, 404);

    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.plugins.settings', [
      'title' => $plugin->labelText().' Settings',
      'adminProjectIdentity' => $this->systemSettings->adminProjectIdentity(),
      'adminBrowserTitle' => $this->systemSettings->adminBrowserTitle($plugin->labelText().' Settings'),
      'plugin' => $plugin,
      'settings' => $plugin->settingsDefinition(),
    ]);
  }
}
