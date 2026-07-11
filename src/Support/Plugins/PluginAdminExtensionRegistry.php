<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Contracts\Auth\Authenticatable;

class PluginAdminExtensionRegistry
{
  public function __construct(
    private readonly PluginRegistry $plugins,
  ) {}

  /**
   * @return array<int, PluginDashboardWidget>
   */
  public function dashboardWidgets(?Authenticatable $user = null): array
  {
    return $this->plugins->dashboardWidgets($user);
  }

  /**
   * @return array<int, PluginSystemCard>
   */
  public function systemCards(?Authenticatable $user = null): array
  {
    return $this->plugins->systemCards($user);
  }
}
