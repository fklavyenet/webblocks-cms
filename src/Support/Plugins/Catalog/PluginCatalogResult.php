<?php

namespace WebBlocks\Cms\Support\Plugins\Catalog;

class PluginCatalogResult
{
  /**
   * @param  array<int, CatalogPlugin>  $plugins
   */
  public function __construct(
    public readonly bool $available,
    public readonly array $plugins,
    public readonly string $baseUrl,
    public readonly string $cmsVersion,
    public readonly ?string $message = null,
  ) {}
}
