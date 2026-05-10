<?php

namespace App\Support\Search;

use App\Models\Block;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\SharedSlot;

trait ReindexesPublicSearch
{
    protected static function refreshSearchForPage(Page|int|null $page, ?string $localeCode = null): void
    {
        if (! $page) {
            return;
        }

        $resolvedPage = $page instanceof Page ? $page : Page::query()->find($page);

        if (! $resolvedPage) {
            return;
        }

        $locale = $localeCode ? Locale::query()->where('code', $localeCode)->first() : null;
        app(PublicSearchIndexer::class)->refreshPage($resolvedPage, $locale);
    }

    protected static function refreshSearchForBlock(Block $block): void
    {
        $page = $block->page ?? $block->page()->first();

        if (! $page instanceof Page) {
            return;
        }

        if ($page->isSharedSlotSourcePage()) {
            $sharedSlotId = (int) data_get($page->settings, 'shared_slot_id');

            if ($sharedSlotId > 0) {
                app(PublicSearchIndexer::class)->refreshSharedSlot($sharedSlotId);
            }

            return;
        }

        app(PublicSearchIndexer::class)->refreshPage($page);
    }

    protected static function refreshSearchForTranslation(PageTranslation $translation): void
    {
        $page = $translation->page ?? $translation->page()->first();

        if (! $page instanceof Page) {
            return;
        }

        static::refreshSearchForPage($page, $translation->locale?->code);
    }

    protected static function refreshSearchForSharedSlot(SharedSlot|int|null $sharedSlot): void
    {
        if (! $sharedSlot) {
            return;
        }

        app(PublicSearchIndexer::class)->refreshSharedSlot($sharedSlot instanceof SharedSlot ? $sharedSlot : (int) $sharedSlot);
    }
}
