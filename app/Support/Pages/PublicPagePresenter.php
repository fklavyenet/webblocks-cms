<?php

namespace App\Support\Pages;

use App\Models\Block;
use App\Models\Page;
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
            'metaDescription' => $this->resolveMetaDescription($page, $translatedTopLevelBlocks),
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

    private function resolveMetaDescription(Page $page, Collection $blocks): ?string
    {
        $summary = $blocks
            ->map(fn (Block $block) => $block->content ?: $block->subtitle)
            ->filter()
            ->first();

        return $summary ? str(strip_tags((string) $summary))->squish()->limit(160)->toString() : config('app.slogan');
    }
}
