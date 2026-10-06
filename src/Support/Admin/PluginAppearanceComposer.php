<?php

namespace WebBlocks\Cms\Support\Admin;

use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use WebBlocks\Cms\Policies\PluginAppearancePolicy;
use WebBlocks\Cms\Support\Plugins\PluginAppearance;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;

class PluginAppearanceComposer
{
  public function __construct(
    private readonly PluginRegistry $plugins,
    private readonly PluginAppearance $appearance,
    private readonly PluginAppearancePolicy $policy,
  ) {}

  public function compose(View $view): void
  {
    $card = null;
    foreach ($this->plugins->all() as $plugin) {
      $details = request()->routeIs('admin.system.plugins.show') && request()->route('plugin') === $plugin->handle();
      $settings = request()->routeIs($this->appearance->settingsRouteName($plugin)) && $this->plugins->isEnabled($plugin->handle());
      if ((! $details && ! $settings) || $plugin->menuItems() === [] || ! $this->policy->update(request()->user(), $plugin)) {
        continue;
      }
      if (! Route::has('admin.system.plugins.appearance.update')) {
        continue;
      }
      if (! $this->appearance->isAvailable()) {
        break;
      }
      $siteId = filter_var(request()->query('site_id'), FILTER_VALIDATE_INT);
      $card = [
        'handle' => $plugin->handle(),
        'slug' => $this->appearance->selectedSlug($plugin),
        'icon_class' => $this->appearance->iconClass($plugin),
        'origin' => $settings ? 'settings' : 'details',
        'site_id' => $siteId !== false && $siteId > 0 ? $siteId : null,
      ];
      break;
    }
    $view->with('pluginAppearanceCard', $card);
  }
}
