<?php

namespace App\Support\Pages;

use App\Models\Block;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\Site;
use App\Support\Blocks\BlockTranslationResolver;
use App\Support\Navigation\NavigationTree;
use Illuminate\Support\Collection;

class PublicPagePresenter
{
    public function __construct(
        private readonly NavigationTree $navigationTree,
        private readonly PageRouteResolver $pageRouteResolver,
        private readonly BlockTranslationResolver $blockTranslationResolver,
    ) {}

    public function present(Page $page): array
    {
        $topLevelBlocks = $page->blocks
            ->whereNull('parent_id')
            ->where('status', 'published')
            ->sortBy('sort_order')
            ->values();

        $translatedTopLevelBlocks = $this->blockTranslationResolver
            ->resolveCollection($topLevelBlocks)
            ->values();

        $slots = $page->slots
            ->sortBy('sort_order')
            ->map(fn (PageSlot $slot) => $this->presentSlot($slot, $translatedTopLevelBlocks, $page->site))
            ->filter(fn (array $slot) => $slot['blocks']->isNotEmpty())
            ->values();

        return [
            'page' => $page,
            'slots' => $slots,
            'headerSlot' => $slots->firstWhere('slug', 'header'),
            'mainSlot' => $slots->firstWhere('slug', 'main'),
            'sidebarSlot' => $slots->firstWhere('slug', 'sidebar'),
            'footerSlot' => $slots->firstWhere('slug', 'footer'),
            'layoutMode' => PublicLayoutMode::forPage($page),
            'metaDescription' => $this->resolveMetaDescription($page, $translatedTopLevelBlocks),
            'homePath' => $this->pageRouteResolver->homePath(),
        ];
    }

    private function presentSlot(PageSlot $slot, Collection $topLevelBlocks, ?Site $site): array
    {
        $slug = $slot->slotType?->slug ?? 'main';
        $blocks = $topLevelBlocks
            ->where('slot_type_id', $slot->slot_type_id)
            ->values();

        return [
            'slug' => $slug,
            'name' => $slot->slotType?->name ?? str($slug)->headline()->toString(),
            'region' => $this->regionFor($slug),
            'view' => $this->viewFor($slug),
            'blocks' => $blocks,
            'chrome' => $this->chromeFor($slug, $blocks, $site),
        ];
    }

    private function regionFor(string $slotSlug): string
    {
        return match ($slotSlug) {
            'header' => 'header',
            'footer' => 'footer',
            'sidebar' => 'sidebar',
            default => 'main',
        };
    }

    private function viewFor(string $slotSlug): string
    {
        return match ($slotSlug) {
            'header' => 'pages.partials.slots.header',
            'footer' => 'pages.partials.slots.footer',
            'sidebar' => 'pages.partials.slots.sidebar',
            default => 'pages.partials.slots.main',
        };
    }

    private function chromeFor(string $slotSlug, Collection $blocks, ?Site $site): array
    {
        return match ($slotSlug) {
            'header' => $this->headerChrome($blocks, $site),
            'footer' => $this->footerChrome($blocks, $site),
            'sidebar' => [
                'label' => 'Sidebar',
            ],
            default => [
                'label' => 'Main content',
            ],
        };
    }

    private function headerChrome(Collection $blocks, ?Site $site): array
    {
        $branding = $blocks->first(fn (Block $block) => in_array($block->typeSlug(), ['heading', 'image', 'rich-text', 'section'], true));
        $menuBlock = $blocks->first(fn (Block $block) => in_array($block->typeSlug(), ['navigation-auto', 'menu'], true) && $block->navigationMenuKey() === NavigationItem::MENU_PRIMARY);
        $mobileMenuBlock = $blocks->first(fn (Block $block) => in_array($block->typeSlug(), ['navigation-auto', 'menu'], true) && $block->navigationMenuKey() === NavigationItem::MENU_MOBILE);
        $actionBlocks = $blocks
            ->filter(fn (Block $block) => $block->typeSlug() === 'button')
            ->values();

        return [
            'branding' => $branding,
            'navigation_block' => $menuBlock,
            'mobile_navigation_block' => $mobileMenuBlock,
            'primary_items' => $this->navigationTree->buildMenuTree(NavigationItem::MENU_PRIMARY, $site)->filter(fn ($item) => $item->isVisible())->values(),
            'mobile_items' => $this->navigationTree->buildMenuTree(NavigationItem::MENU_MOBILE, $site)->filter(fn ($item) => $item->isVisible())->values(),
            'actions' => $actionBlocks,
        ];
    }

    private function footerChrome(Collection $blocks, ?Site $site): array
    {
        $footerNavBlock = $blocks->first(fn (Block $block) => in_array($block->typeSlug(), ['navigation-auto', 'menu'], true) && $block->navigationMenuKey() === NavigationItem::MENU_FOOTER);
        $legalNavBlock = $blocks->first(fn (Block $block) => in_array($block->typeSlug(), ['navigation-auto', 'menu'], true) && $block->navigationMenuKey() === NavigationItem::MENU_LEGAL);

        $supportingBlocks = $blocks
            ->reject(fn (Block $block) => in_array($block->id, array_filter([
                $footerNavBlock?->id,
                $legalNavBlock?->id,
            ]), true))
            ->values();

        return [
            'footer_navigation_block' => $footerNavBlock,
            'legal_navigation_block' => $legalNavBlock,
            'footer_items' => $this->navigationTree->buildMenuTree(NavigationItem::MENU_FOOTER, $site)->filter(fn ($item) => $item->isVisible())->values(),
            'legal_items' => $this->navigationTree->buildMenuTree(NavigationItem::MENU_LEGAL, $site)->filter(fn ($item) => $item->isVisible())->values(),
            'supporting_blocks' => $supportingBlocks,
        ];
    }

    private function resolveMetaDescription(Page $page, Collection $blocks): ?string
    {
        $summary = $blocks
            ->map(fn (Block $block) => $block->content ?: $block->subtitle)
            ->filter()
            ->first();

        return $summary ? str(strip_tags((string) $summary))->squish()->limit(160)->toString() : config('app.slogan');
    }
}
