<?php

namespace WebBlocks\Cms\Support\Blocks;

use Illuminate\Support\Collection;
use WebBlocks\Cms\Models\Block;

final class PageBlockStructure
{
  public function siblings(Collection $blocks, int $slotTypeId, ?int $parentId): Collection
  {
    return $blocks->where('slot_type_id', $slotTypeId)->whereStrict('parent_id', $parentId);
  }

  public function acceptsChild(?Block $parent, string $type): bool
  {
    return ! $parent || ($parent->canAcceptMoreChildren() && $parent->canAcceptChildType($type));
  }

  /** @param list<int> $orderedIds */
  public function containsExactly(Collection $siblings, array $orderedIds): bool
  {
    return $siblings->pluck('id')->sort()->values()->all() === collect($orderedIds)->sort()->values()->all();
  }

  /** Persist an order already checked against the locked sibling group. */
  public function saveOrder(Collection $siblings, array $orderedIds): void
  {
    $byId = $siblings->keyBy('id');
    foreach ($orderedIds as $position => $id) {
      $block = $byId->get($id);
      if ($block->sort_order !== $position) {
        $block->update(['sort_order' => $position]);
      }
    }
  }

  /** The caller supplies locked blocks and movable blocks keyed by block ID. */
  public function move(Block $block, int $slotTypeId, ?Block $parent, Collection $blocks, Collection $movable): bool
  {
    if ($parent && ($parent->page_id !== $block->page_id || $parent->slot_type_id !== $slotTypeId)) {
      return false;
    }
    if ($block->slot_type_id === $slotTypeId && $block->parent_id === $parent?->id) {
      return true;
    }

    $subtree = collect([$block]);
    $visited = [];
    $children = $blocks->groupBy('parent_id');
    for ($index = 0; $index < $subtree->count(); $index++) {
      $node = $subtree[$index];
      if (isset($visited[$node->id]) || $node->id === $parent?->id || ! $movable->has($node->id)
        || $node->slot_type_id !== $block->slot_type_id) {
        return false;
      }
      $visited[$node->id] = true;
      foreach ($children->get($node->id, []) as $child) {
        $subtree->push($child);
      }
    }

    if ($blocks->get($block->parent_id)?->typeSlug() === 'split' || ! $this->acceptsChild($parent, $block->typeSlug())) {
      return false;
    }

    $order = ($this->siblings($blocks, $slotTypeId, $parent?->id)->max('sort_order') ?? -1) + 1;
    $block->update(['parent_id' => $parent?->id, 'slot_type_id' => $slotTypeId, 'sort_order' => $order]);
    foreach ($subtree->skip(1) as $child) {
      if ($child->slot_type_id !== $slotTypeId) {
        $child->update(['slot_type_id' => $slotTypeId]);
      }
    }

    return true;
  }
}
