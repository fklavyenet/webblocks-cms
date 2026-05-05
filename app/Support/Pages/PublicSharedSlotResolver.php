<?php

namespace App\Support\Pages;

use App\Models\Block;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\SharedSlot;
use App\Models\SharedSlotBlock;
use App\Support\Blocks\BlockTranslationResolver;
use Illuminate\Support\Collection;

class PublicSharedSlotResolver
{
    public function __construct(
        private readonly BlockTranslationResolver $blockTranslationResolver,
    ) {}

    public function resolve(PageSlot $slot): Collection
    {
        $page = $slot->page ?? $slot->page()->first();
        $sharedSlot = $slot->sharedSlot;

        if (! $page || ! $sharedSlot instanceof SharedSlot || ! $this->isCompatible($slot, $sharedSlot)) {
            return collect();
        }

        $assignments = $sharedSlot->relationLoaded('slotBlocks')
            ? $sharedSlot->slotBlocks
            : $sharedSlot->slotBlocks()->with($this->blockRelations())->get();

        if ($assignments->isEmpty()) {
            return collect();
        }

        $assignments = $assignments
            ->filter(fn (SharedSlotBlock $assignment) => $assignment->block instanceof Block && $assignment->block->status === 'published')
            ->keyBy('id');

        if ($assignments->isEmpty()) {
            return collect();
        }

        $childrenByParent = $assignments->groupBy('parent_id');

        return $this->buildTree($childrenByParent, null)
            ->values();
    }

    private function isCompatible(PageSlot $slot, SharedSlot $sharedSlot): bool
    {
        $page = $slot->page ?? $slot->page()->first();

        if (! $page) {
            return false;
        }

        return $sharedSlot->isCompatibleWithPageSlot($page, $slot->slotSlug());
    }

    private function buildTree(Collection $childrenByParent, ?int $parentId): Collection
    {
        return $childrenByParent->get($parentId, collect())
            ->sortBy(fn (SharedSlotBlock $assignment) => sprintf('%010d-%010d', (int) $assignment->sort_order, (int) $assignment->id))
            ->values()
            ->map(function (SharedSlotBlock $assignment) use ($childrenByParent) {
                $block = clone $assignment->block;
                $block->setRelation('children', $this->buildTree($childrenByParent, $assignment->id));

                return $this->blockTranslationResolver->resolve($block);
            });
    }

    private function blockRelations(): array
    {
        return [
            'block.blockType',
            'block.slotType',
            'block.asset',
            'block.blockAssets.asset',
            'block.textTranslations',
            'block.buttonTranslations',
            'block.imageTranslations',
            'block.contactFormTranslations',
        ];
    }
}
