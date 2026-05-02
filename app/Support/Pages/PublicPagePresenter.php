<?php

namespace App\Support\Pages;

use App\Models\Block;
use App\Models\Page;
use App\Models\PageSlot;
use App\Support\Blocks\BlockTranslationResolver;
use Illuminate\Support\Collection;

class PublicPagePresenter
{
    public function __construct(
        private readonly BlockTranslationResolver $blockTranslationResolver,
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
            'metaDescription' => $this->resolveMetaDescription($page, $translatedTopLevelBlocks),
        ];
    }

    private function presentSlot(PageSlot $slot, Collection $topLevelBlocks): array
    {
        $slug = $slot->slotType?->slug ?? 'main';
        $blocks = $topLevelBlocks
            ->where('slot_type_id', $slot->slot_type_id)
            ->values();
        $wrapperPreset = $slot->wrapperPreset();

        if ($wrapperPreset === 'default' && $slot->page?->publicShellPreset() === 'docs') {
            $wrapperPreset = match ($slug) {
                'header' => 'docs-navbar',
                'sidebar' => 'docs-sidebar',
                'main' => 'docs-main',
                default => 'default',
            };
        }

        return [
            'slug' => $slug,
            'name' => $slot->slotType?->name ?? str($slug)->headline()->toString(),
            'wrapper_preset' => $wrapperPreset,
            'wrapper_element' => $slot->wrapperElement(),
            'blocks' => $blocks,
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
