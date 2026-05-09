<?php

namespace App\Support\Pages;

use App\Models\Block;
use App\Models\Page;
use App\Models\PageAsset;
use App\Models\PageTranslation;
use App\Models\PageSlot;
use App\Support\Blocks\BlockTranslationResolver;
use App\Support\PublicRendering\SlotWrapperResolver;
use App\Support\Pages\PageRouteResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PublicPagePresenter
{
    public function __construct(
        private readonly BlockTranslationResolver $blockTranslationResolver,
        private readonly PageAssetRenderer $pageAssetRenderer,
        private readonly PublicSharedSlotResolver $publicSharedSlotResolver,
        private readonly SlotWrapperResolver $slotWrapperResolver,
        private readonly PageRouteResolver $pageRouteResolver,
    ) {}

    public function present(Page $page): array
    {
        $topLevelBlocks = $page->blocks
            ->whereNull('parent_id')
            ->where('status', 'published')
            ->sortBy(fn (Block $block) => sprintf('%010d-%010d', (int) $block->sort_order, (int) $block->id))
            ->values();

        $translatedTopLevelBlocks = $this->blockTranslationResolver
            ->resolveCollection($topLevelBlocks, site: $page->site)
            ->values();

        $slots = $page->slots
            ->sortBy(fn (PageSlot $slot) => sprintf('%010d-%010d', (int) $slot->sort_order, (int) $slot->id))
            ->map(fn (PageSlot $slot) => $this->presentSlot($slot, $translatedTopLevelBlocks))
            ->values();

        return [
            'page' => $page,
            'slots' => $slots,
            'headPageAssets' => $this->pageAssetRenderer->headAssetsFor($page),
            'bodyEndPageAssets' => $this->pageAssetRenderer->bodyEndAssetsFor($page),
            'publicMeta' => $this->publicMeta($page),
        ];
    }

    public function publicMeta(?Page $page = null): array
    {
        $site = $page?->site;
        $translation = $page?->currentTranslation;
        $siteName = $site?->publicDisplayName() ?? $site?->name ?? config('app.name');
        $siteLabel = $this->siteLabel($site);
        $siteSeoTitle = trim((string) ($site?->seo_title ?? ''));
        $siteSeoDescription = trim((string) ($site?->seo_description ?? ''));
        $siteSeoKeywords = trim((string) ($site?->seo_keywords ?? ''));
        $pageTitle = $this->trimmed($translation?->name);
        $seoTitle = $this->trimmed($translation?->seo_title);
        $pageLabel = $seoTitle ?? $pageTitle;
        $seoDescription = $this->trimmed($translation?->seo_description);
        $seoKeywords = $this->trimmed($translation?->seo_keywords);
        $ogTitle = $this->trimmed($translation?->og_title);
        $ogDescription = $this->trimmed($translation?->og_description);
        $ogImage = $this->trimmed($translation?->ogImage?->url())
            ?? $this->trimmed($site?->socialImageAsset?->url());
        $title = $this->composeSiteFirstTitle($siteLabel, $pageLabel)
            ?? config('app.name');
        $resolvedOgTitle = $ogTitle
            ?? $this->composeSiteFirstTitle($siteLabel, $pageLabel)
            ?? config('app.name');

        return [
            'site_name' => $siteName,
            'site_tagline' => trim((string) ($site?->tagline ?? config('app.slogan'))),
            'site_label' => $siteLabel,
            'title' => $title,
            'canonical_url' => $page ? $this->pageRouteResolver->canonicalUrlFor($page, $translation?->locale?->code, $site) : null,
            'meta_description' => $seoDescription ?? $siteSeoDescription,
            'meta_keywords' => $seoKeywords ?? $siteSeoKeywords,
            'favicon_url' => $this->trimmed($site?->faviconAsset?->url()),
            'og_title' => $resolvedOgTitle,
            'og_description' => $ogDescription
                ?? $seoDescription
                ?? $siteSeoDescription,
            'og_image' => $ogImage,
            'og_url' => $page ? $this->pageRouteResolver->canonicalUrlFor($page, $translation?->locale?->code, $site) : null,
            'og_site_name' => $siteName,
        ];
    }

    public function publicSiteLabel(?Page $page = null): ?string
    {
        return $this->siteLabel($page?->site);
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

    private function siteLabel(mixed $site): ?string
    {
        return $this->trimmed($site?->display_name)
            ?? $this->trimmed($site?->seo_title)
            ?? $this->trimmed($site?->name);
    }

    private function composeSiteFirstTitle(?string $siteLabel, ?string $pageLabel): ?string
    {
        if ($siteLabel !== null && $pageLabel !== null) {
            $siteNormalized = Str::lower($siteLabel);
            $pageNormalized = Str::lower($pageLabel);

            if ($siteNormalized === $pageNormalized || Str::contains($pageNormalized, $siteNormalized)) {
                return $pageLabel;
            }

            return $siteLabel.' · '.$pageLabel;
        }

        return $siteLabel ?? $pageLabel;
    }
}
