<?php

namespace App\Models\Concerns;

use App\Models\Block;
use App\Models\Locale;
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
                $localeIsEnabled = Locale::query()
                    ->whereKey($translation->locale_id)
                    ->where('is_enabled', true)
                    ->exists();

                if (! $localeIsEnabled) {
                    throw new \RuntimeException('Block translation locale must be globally enabled for layout-owned blocks.');
                }

                return;
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
