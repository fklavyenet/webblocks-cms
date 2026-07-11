<?php

namespace WebBlocks\Cms\Support\Plugins\Catalog;

class PluginCatalogDetailResult
{
  public function __construct(
    public readonly bool $available,
    public readonly ?CatalogPlugin $plugin,
    public readonly string $baseUrl,
    public readonly string $cmsVersion,
    public readonly ?string $message = null,
  ) {}
}
