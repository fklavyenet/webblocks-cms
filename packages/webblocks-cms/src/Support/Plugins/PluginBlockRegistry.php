<?php

namespace WebBlocks\Cms\Support\Plugins;

class PluginBlockRegistry
{
  public function __construct(
    private readonly PluginRegistry $plugins,
  ) {}

  /**
   * @return array<string, PluginBlockTypeDefinition>
   */
  public function discoverableBlockTypes(): array
  {
    return $this->plugins->pluginBlockTypes();
  }

  /**
   * @return array<string, PluginBlockPackDefinition>
   */
  public function discoverableBlockPacks(): array
  {
    return $this->plugins->pluginBlockPacks();
  }
}
