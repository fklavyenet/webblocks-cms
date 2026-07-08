<?php

use WebBlocks\Cms\Support\Translations\CmsTranslator;

if (! function_exists('cms_trans')) {
  function cms_trans(string $key, ?string $locale = null, array $replace = []): string
  {
    return app(CmsTranslator::class)->get($key, $locale, $replace);
  }
}
