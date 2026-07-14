<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support;

use WebBlocks\Cms\Support\Plugins\PluginHealthResult;

class WebBlocksCommerceHealth
{
  public function __construct(
    private readonly WebBlocksCommerceSchema $schema,
  ) {}

  public function health(): PluginHealthResult
  {
    if (! class_exists(\NumberFormatter::class)) {
      return PluginHealthResult::warning('WebBlocks Commerce requires the PHP intl extension for locale-aware currency formatting.');
    }

    if (! $this->schema->isReady()) {
      return PluginHealthResult::warning($this->schema->message());
    }

    return PluginHealthResult::healthy('Commerce tables are ready. Public buy URLs are available; real payment collection still requires a configured gateway adapter.');
  }
}
