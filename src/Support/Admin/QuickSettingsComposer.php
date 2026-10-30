<?php

namespace WebBlocks\Cms\Support\Admin;

use Illuminate\View\View;
use WebBlocks\Cms\Support\Plugins\PluginAdminExtensionRegistry;

class QuickSettingsComposer
{
  public function __construct(private readonly PluginAdminExtensionRegistry $extensions) {}

  public function compose(View $view): void
  {
    $view->with('adminQuickSettings', $this->extensions->quickSettings(request()->user()));
  }
}
