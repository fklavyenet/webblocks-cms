<?php

namespace WebBlocks\Cms\Support\Translations;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\Locales\LocaleResolver;

class PublicLocaleContext
{
  public function __construct(
    private readonly LocaleResolver $localeResolver,
  ) {}

  public function forBlockSource(Block $block, ?string $sourceUrl = null): string
  {
    $path = parse_url((string) $sourceUrl, PHP_URL_PATH);
    $path = is_string($path) && $path !== '' ? $path : null;

    $block->loadMissing(['page.translations.locale']);

    if ($path && $block->page) {
      foreach ($block->page->translations as $translation) {
        $localeCode = $translation->locale?->code;
        $localizedPath = $localeCode ? '/'.$localeCode.$translation->path : null;

        if ($translation->path === $path || '/'.$translation->slug === $path || $localizedPath === $path) {
          return $translation->locale?->code ?? $this->localeResolver->default()->code;
        }
      }
    }

    return $this->localeResolver->default()->code;
  }
}
