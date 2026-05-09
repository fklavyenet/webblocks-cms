<?php

namespace App\Support\Blocks;

use App\Models\Block;
use Illuminate\Support\Collection;

class BlockDeletionManager
{
    public function slotMetadata(int $pageId, int $slotTypeId): array
    {
        $scopedBlocks = $this->scopedBlocksForSlot($pageId, $slotTypeId);
        $topLevelCount = $scopedBlocks->whereNull('parent_id')->count();

        return [
            'top_level_count' => $topLevelCount,
            'descendant_count' => max($scopedBlocks->count() - $topLevelCount, 0),
            'total_count' => $scopedBlocks->count(),
        ];
    }

    public function metadata(Block $block): array
    {
        $scopedBlocks = $this->scopedBlocks($block);
        $descendantIds = $this->descendantIds($block, $scopedBlocks);
        $directChildCount = $scopedBlocks->where('parent_id', $block->id)->count();

        return [
            'has_children' => $directChildCount > 0,
            'direct_child_count' => $directChildCount,
            'descendant_count' => $descendantIds->count(),
            'descendant_ids' => $descendantIds,
        ];
    }

    public function recursiveDeleteOrder(Block $block): Collection
    {
        $scopedBlocks = $this->scopedBlocks($block);
        $descendantIds = $this->descendantIds($block, $scopedBlocks);

        if ($descendantIds->isEmpty()) {
            return collect([$block]);
        }

        $blocksById = $scopedBlocks->keyBy('id');
        $orderedDescendants = $descendantIds
            ->map(fn (int $id) => $blocksById->get($id))
            ->filter()
            ->sortByDesc(fn (Block $candidate) => $this->depthWithinScope($candidate, $blocksById))
            ->values();

        return $orderedDescendants
            ->push($block)
            ->values();
    }

    public function descendantIds(Block $block, ?Collection $scopedBlocks = null): Collection
    {
        $scopedBlocks ??= $this->scopedBlocks($block);
        $childrenByParent = $scopedBlocks->groupBy('parent_id');
        $descendantIds = collect();
        $stack = collect([$block->id]);

        while ($stack->isNotEmpty()) {
            $currentId = $stack->pop();
            $children = $childrenByParent->get($currentId, collect());

            foreach ($children as $child) {
                if ($descendantIds->contains($child->id)) {
                    continue;
                }

                $descendantIds->push((int) $child->id);
                $stack->push((int) $child->id);
            }
        }

        return $descendantIds->values();
    }

    public function scopedBlocks(Block $block): Collection
    {
        return $this->scopedBlocksForSlot((int) $block->page_id, (int) $block->slot_type_id);
    }

    public function scopedBlocksForSlot(int $pageId, int $slotTypeId): Collection
    {
        return Block::query()
            ->where('page_id', $pageId)
            ->where('slot_type_id', $slotTypeId)
            ->get(['id', 'parent_id', 'page_id', 'slot_type_id']);
    }

    private function depthWithinScope(Block $block, Collection $blocksById): int
    {
        $depth = 0;
        $ancestorId = $block->parent_id;

        while ($ancestorId && $blocksById->has($ancestorId)) {
            $depth++;
            $ancestorId = $blocksById->get($ancestorId)?->parent_id;
        }

        return $depth;
    }
}
