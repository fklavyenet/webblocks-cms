<?php

namespace App\Models\Concerns;

use App\Models\Block;
use App\Models\Site;

trait ValidatesBlockTranslationLocale
{
    protected static function bootValidatesBlockTranslationLocale(): void
    {
        static::saving(function ($translation): void {
            $siteId = $translation->block?->page?->site_id
                ?? ($translation->block_id
                    ? Block::query()
                        ->join('pages', 'pages.id', '=', 'blocks.page_id')
                        ->where('blocks.id', $translation->block_id)
                        ->value('pages.site_id')
                    : null);

            if (! $siteId) {
                throw new \RuntimeException('Block translations must belong to a block page site.');
            }

            $localeIsEnabled = Site::query()
                ->whereKey($siteId)
                ->whereHas('enabledLocales', fn ($query) => $query->where('locales.id', $translation->locale_id))
                ->exists();

            if (! $localeIsEnabled) {
                throw new \RuntimeException('Block translation locale must be enabled for the block page site.');
            }
        });
    }
}
