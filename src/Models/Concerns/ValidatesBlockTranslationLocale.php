<?php

namespace WebBlocks\Cms\Models\Concerns;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Translations\SiteLocaleValidation;

trait ValidatesBlockTranslationLocale
{
  protected static function bootValidatesBlockTranslationLocale(): void
  {
    static::saving(function ($translation): void {
      $siteId = $translation->block?->page?->site_id
        ?? ($translation->block_id
          ? Block::query()
            ->join((new Page)->getTable(), (new Page)->qualifyColumn('id'), '=', (new Block)->qualifyColumn('page_id'))
            ->where((new Block)->qualifyColumn('id'), $translation->block_id)
            ->value((new Page)->qualifyColumn('site_id'))
          : null);

      if (! $siteId) {
        throw new \RuntimeException('Block translations must belong to a block page site.');
      }

      $localeIsEnabled = app(SiteLocaleValidation::class)->allows((int) $siteId, $translation->locale_id);

      if (! $localeIsEnabled) {
        throw new \RuntimeException('Block translation locale must be enabled for the block page site.');
      }
    });
  }
}
