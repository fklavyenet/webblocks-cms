<?php

namespace WebBlocks\Cms\Support\Plugins;

class PluginPublicAssetRegistry
{
  public function __construct(
    private readonly PluginRegistry $plugins,
  ) {}

  /**
   * @return array<int, PluginPublicAsset>
   */
  public function head(): array
  {
    return $this->plugins->publicAssets(PluginPublicAsset::LOCATION_HEAD);
  }

  /**
   * @return array<int, PluginPublicAsset>
   */
  public function bodyEnd(): array
  {
    return $this->plugins->publicAssets(PluginPublicAsset::LOCATION_BODY_END);
  }
}
