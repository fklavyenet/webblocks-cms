<?php

namespace WebBlocks\Cms\Models\Concerns;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;

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

      $localeIsEnabled = Site::query()
        ->whereKey($siteId)
        ->whereHas('enabledLocales', fn ($query) => $query->where((new Locale)->qualifyColumn('id'), $translation->locale_id))
        ->exists();

      if (! $localeIsEnabled) {
        throw new \RuntimeException('Block translation locale must be enabled for the block page site.');
      }
    });
  }
}
