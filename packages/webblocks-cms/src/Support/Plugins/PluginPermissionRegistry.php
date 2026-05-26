<?php

namespace WebBlocks\Cms\Support\Plugins;

class PluginPermissionRegistry
{
  public function __construct(
    private readonly PluginRegistry $plugins,
  ) {}

  /**
   * @return array<string, array<string, PluginPermission>>
   */
  public function active(): array
  {
    return $this->plugins->permissions(enabledOnly: true);
  }

  /**
   * @return array<string, array<string, PluginPermission>>
   */
  public function disabled(): array
  {
    return $this->plugins->disabledPermissions();
  }
}
