<?php

namespace WebBlocks\Cms\Support\Search;

use App\Models\Locale;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SearchablePageResolver
{
    public function query(?Site $site = null, ?int $pageId = null): Builder
    {
        return Page::query()
            ->where('status', Page::STATUS_PUBLISHED)
            ->where('page_type', '!=', Page::TYPE_SHARED_SLOT_SOURCE)
            ->when($site, fn (Builder $query) => $query->where('site_id', $site->id))
            ->when($pageId, fn (Builder $query) => $query->whereKey($pageId));
    }

    public function shouldIndex(Page $page): bool
    {
        return $page->isPublished() && ! $page->isSharedSlotSourcePage();
    }

    public function loadForIndexing(Page $page): Page
    {
        $page->loadMissing([
            'site.locales',
            'site.siteVariables',
            'translations.locale',
            'slots.slotType',
            'slots.sharedSlot.slotBlocks.block.blockType',
            'slots.sharedSlot.slotBlocks.block.slotType',
            'slots.sharedSlot.slotBlocks.block.asset',
            'slots.sharedSlot.slotBlocks.block.blockAssets.asset',
            'slots.sharedSlot.slotBlocks.block.textTranslations',
            'slots.sharedSlot.slotBlocks.block.buttonTranslations',
            'slots.sharedSlot.slotBlocks.block.imageTranslations',
            'slots.sharedSlot.slotBlocks.block.contactFormTranslations',
            'blocks' => fn ($query) => $query
                ->where('status', 'published')
                ->with($this->publishedBlockRelations())
                ->orderBy('sort_order')
                ->orderBy('id'),
        ]);

        return $page;
    }

    public function searchableLocales(Page $page, ?Locale $onlyLocale = null): Collection
    {
        return $this->candidateLocales($page, $onlyLocale)
            ->filter(function (Locale $locale) use ($page) {
                return $page->translationForLocale($locale)?->exists
                    && $page->publicPath($locale->code) !== null;
            })
            ->values();
    }

    public function candidateLocales(Page $page, ?Locale $onlyLocale = null): Collection
    {
        $locales = $page->availableSiteLocales();

        if (! $onlyLocale) {
            return $locales;
        }

        return $locales->where('id', $onlyLocale->id)->values();
    }

    private function publishedBlockRelations(): array
    {
        return [
            'blockType',
            'slotType',
            'asset',
            'blockAssets.asset',
            'textTranslations',
            'buttonTranslations',
            'imageTranslations',
            'contactFormTranslations',
            'children' => fn ($query) => $query
                ->where('status', 'published')
                ->with($this->publishedBlockRelations())
                ->orderBy('sort_order')
                ->orderBy('id'),
        ];
    }
}
