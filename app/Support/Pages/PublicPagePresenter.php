<?php

namespace App\Support\Pages;

use App\Models\Block;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\PageSlot;
use App\Support\Blocks\BlockTranslationResolver;
use App\Support\PublicRendering\SlotWrapperResolver;
use Illuminate\Support\Collection;

class PublicPagePresenter
{
    public function __construct(
        private readonly BlockTranslationResolver $blockTranslationResolver,
        private readonly PublicSharedSlotResolver $publicSharedSlotResolver,
        private readonly SlotWrapperResolver $slotWrapperResolver,
    ) {}

    public function present(Page $page): array
    {
        $topLevelBlocks = $page->blocks
            ->whereNull('parent_id')
            ->where('status', 'published')
            ->sortBy(fn (Block $block) => sprintf('%010d-%010d', (int) $block->sort_order, (int) $block->id))
            ->values();

        $translatedTopLevelBlocks = $this->blockTranslationResolver
            ->resolveCollection($topLevelBlocks)
            ->values();

        $slots = $page->slots
            ->sortBy(fn (PageSlot $slot) => sprintf('%010d-%010d', (int) $slot->sort_order, (int) $slot->id))
            ->map(fn (PageSlot $slot) => $this->presentSlot($slot, $translatedTopLevelBlocks))
            ->values();

        return [
            'page' => $page,
            'slots' => $slots,
            'publicMeta' => $this->publicMeta($page),
        ];
    }

    public function publicMeta(?Page $page = null): array
    {
        $site = $page?->site;
        $translation = $page?->currentTranslation;
        $siteName = $site?->publicDisplayName() ?? $site?->name ?? config('app.name');
        $siteSeoTitle = trim((string) ($site?->seo_title ?? ''));
        $siteSeoDescription = trim((string) ($site?->seo_description ?? ''));
        $siteSeoKeywords = trim((string) ($site?->seo_keywords ?? ''));
        $pageTitle = $this->trimmed($translation?->name);
        $seoTitle = $this->trimmed($translation?->seo_title);
        $seoDescription = $this->trimmed($translation?->seo_description);
        $seoKeywords = $this->trimmed($translation?->seo_keywords);
        $ogTitle = $this->trimmed($translation?->og_title);
        $ogDescription = $this->trimmed($translation?->og_description);
        $ogImage = $this->trimmed($translation?->ogImage?->url())
            ?? $this->trimmed($site?->socialImageAsset?->url());
        $title = $seoTitle
            ?? $pageTitle
            ?? $siteSeoTitle
            ?? $this->trimmed($siteName)
            ?? config('app.name');

        return [
            'site_name' => $siteName,
            'site_tagline' => trim((string) ($site?->tagline ?? config('app.slogan'))),
            'title' => $title,
            'meta_description' => $seoDescription ?? $siteSeoDescription,
            'meta_keywords' => $seoKeywords ?? $siteSeoKeywords,
            'favicon_url' => $this->trimmed($site?->faviconAsset?->url()),
            'og_title' => $ogTitle
                ?? $seoTitle
                ?? $pageTitle
                ?? $siteSeoTitle
                ?? $this->trimmed($siteName)
                ?? config('app.name'),
            'og_description' => $ogDescription
                ?? $seoDescription
                ?? $siteSeoDescription,
            'og_image' => $ogImage,
            'og_site_name' => $siteName,
        ];
    }

    private function presentSlot(PageSlot $slot, Collection $topLevelBlocks): array
    {
        $page = $slot->page ?? $slot->page()->firstOrFail();
        $slug = $slot->slotType?->slug ?? 'main';
        $blocks = $this->applyRenderContext($this->resolveSlotBlocks($slot, $topLevelBlocks), $page, $slug);
        $wrapper = $this->slotWrapperResolver->resolve($page, $slot);

        return [
            'slug' => $slug,
            'name' => $slot->slotType?->name ?? str($slug)->headline()->toString(),
            'wrapper' => [
                'preset' => $wrapper['preset'],
                'element' => $wrapper['element'],
                'attributes' => $wrapper['attributes'],
            ],
            'blocks' => $blocks,
        ];
    }

    private function resolveSlotBlocks(PageSlot $slot, Collection $topLevelBlocks): Collection
    {
        if ($slot->usesPageOwnedBlocks()) {
            return $topLevelBlocks->where('slot_type_id', $slot->slot_type_id)->values();
        }

        if (PageSlot::normalizeRuntimeSourceType($slot->source_type) === PageSlot::SOURCE_TYPE_SHARED_SLOT) {
            return $this->publicSharedSlotResolver->resolve($slot);
        }

        return collect();
    }

    private function applyRenderContext(Collection $blocks, Page $page, string $slotSlug): Collection
    {
        return $blocks
            ->map(function (Block $block) use ($page, $slotSlug) {
                $block->setRelation('renderPage', $page);
                $block->setAttribute('render_locale_code', $page->currentTranslation?->locale?->code);
                $block->setAttribute('render_slot_slug', $slotSlug);

                if ($block->relationLoaded('children')) {
                    $children = $block->getRelation('children');

                    if ($children instanceof Collection) {
                        $block->setRelation('children', $this->applyRenderContext($children, $page, $slotSlug));
                    }
                }

                return $block;
            })
            ->values();
    }

    private function trimmed(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
