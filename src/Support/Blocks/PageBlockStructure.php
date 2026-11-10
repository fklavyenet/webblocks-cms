<?php

namespace WebBlocks\Cms\Support\Blocks;

use Illuminate\Support\Collection;
use RuntimeException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\ContentSources\ContentSourceAuthoring;
use WebBlocks\Cms\Support\ContentSources\ContentSourceAuthoringException;

final class PageBlockStructure
{
  public function parentBelongsToSlot(Block $parent, int $pageId, int $slotTypeId): bool
  {
    return $parent->page_id === $pageId && $parent->slot_type_id === $slotTypeId;
  }

  public function siblings(Collection $blocks, int $slotTypeId, ?int $parentId): Collection
  {
    return $blocks->where('slot_type_id', $slotTypeId)->whereStrict('parent_id', $parentId);
  }

  public function acceptsChild(?Block $parent, string $type): bool
  {
    return ! $parent || ($parent->canAcceptMoreChildren() && $parent->canAcceptChildType($type));
  }

  public function preservesSplitChildCount(int $before, int $after): bool
  {
    return $before !== Block::SPLIT_CHILD_COUNT || $after === Block::SPLIT_CHILD_COUNT;
  }

  public function needsSplitReplacement(Block $block, Collection $blocks): bool
  {
    $parent = $blocks->get($block->parent_id);

    return $block->slot_type_id !== null && $parent?->typeSlug() === 'split'
      && $this->parentBelongsToSlot($parent, $block->page_id, $block->slot_type_id)
      && $this->siblings($blocks, $block->slot_type_id, $parent->id)->count() === Block::SPLIT_CHILD_COUNT;
  }

  public function splitReplacementCandidates(Collection $blocks, ?Block $block): Collection
  {
    $byId = $blocks->keyBy('id');
    if (! $block?->id || ! $this->needsSplitReplacement($block, $byId)) {
      return collect();
    }

    return $blocks->filter(fn (Block $candidate) => $this->allowsSplitExchange(
      $block, $byId->get($candidate->parent_id), $candidate, $byId, (string) $block->typeSlug(),
    ));
  }

  private function allowsSplitExchange(Block $block, ?Block $parent, Block $replacement, Collection $blocks, string $outgoingType): bool
  {
    $split = $blocks->get($block->parent_id);

    return $split && $parent?->id !== $split->id
      && $this->parentBelongsToSlot($replacement, $block->page_id, $block->slot_type_id)
      && $replacement->parent_id === $parent?->id
      && (! $parent || $this->parentBelongsToSlot($parent, $block->page_id, $block->slot_type_id))
      && $this->placementChain($replacement, $blocks) !== null
      && $this->placementChain($split, $blocks) !== null
      && ! $this->wouldCreateCycle($block, $replacement, $blocks)
      && ! $this->wouldCreateCycle($replacement, $split, $blocks)
      && $this->allowsPlacement($parent, $outgoingType)
      && $this->allowsPlacement($split, (string) $replacement->typeSlug());
  }

  /** Both branches are from the same locked slot and occupy each other's position. */
  public function exchangeSplitChild(Block $block, ?Block $parent, Block $replacement, Collection $blocks, string $outgoingType): bool
  {
    if (! $this->needsSplitReplacement($block, $blocks) || ! $this->allowsSplitExchange($block, $parent, $replacement, $blocks, $outgoingType)) {
      return false;
    }

    $split = $blocks->get($block->parent_id);
    $incoming = (clone $replacement)->fill(['parent_id' => $split->id])->setRelation('parent', $split);
    try {
      app(ContentSourceAuthoring::class)->assertPlacement($incoming);
    } catch (ContentSourceAuthoringException) {
      return false;
    }

    $position = $replacement->sort_order;
    if (! $replacement->update(['parent_id' => $split->id, 'sort_order' => $block->sort_order])
      || ! $block->update(['parent_id' => $parent?->id, 'sort_order' => $position])) {
      throw new RuntimeException('The Split exchange could not be saved.');
    }

    return true;
  }

  public function allowsPlacement(?Block $parent, string $type): bool
  {
    $requiredParents = match ($type) {
      'navbar-brand', 'navbar-navigation' => [], // The model checks for a Navbar anywhere above the child.
      'card_header', 'card_body', 'card_footer' => ['card'],
      'slide' => ['slider'],
      'link-list-item' => ['link-list'],
      'sidebar-nav-item' => ['sidebar-navigation', 'sidebar-nav-group'],
      'sidebar-nav-group' => ['sidebar-navigation'],
      default => null,
    };

    if ($parent === null) {
      return $type !== '' && ($requiredParents === null || $type === 'link-list-item');
    }

    return ($requiredParents === null || $requiredParents === [] || in_array($parent->typeSlug(), $requiredParents, true))
      && $parent->canAcceptChildType($type);
  }

  /** @return list<Block>|null The root-first path, or null for a broken chain. */
  public function placementChain(Block $block, ?Collection $blocks = null, bool $lock = false): ?array
  {
    $chain = [];
    $seen = [];
    $cursor = $block;
    while ($cursor) {
      if (isset($seen[$cursor->id]) || ! $this->parentBelongsToSlot($cursor, $block->page_id, $block->slot_type_id)) {
        return null;
      }
      $seen[$cursor->id] = true;
      $chain[] = $cursor;
      if ($cursor->parent_id === null) {
        return array_reverse($chain);
      }

      $cursor = $blocks !== null ? $blocks->get($cursor->parent_id) : $cursor->parent()
        ->where('page_id', $block->page_id)
        ->where('slot_type_id', $block->slot_type_id)
        ->when($lock, fn ($query) => $query->lockForUpdate())->first();
    }

    return null;
  }

  /** @param list<Block> $chain A complete root-first path. */
  public function allowsPlacementChain(array $chain): bool
  {
    $parent = null;
    foreach ($chain as $block) {
      if (! $this->allowsPlacement($parent, (string) $block->typeSlug())) {
        return false;
      }
      $block->setRelation('parent', $parent);
      $parent = $block;
    }

    return true;
  }

  public function nextSortOrder(?int $lastOrder, int $firstOrder = 0): int
  {
    return $lastOrder === null ? $firstOrder : $lastOrder + 1;
  }

  public function hasSingleParentGroup(Collection $blocks): bool
  {
    return $blocks->pluck('parent_id')->uniqueStrict()->count() === 1;
  }

  /** Reject both a descendant destination and an already cyclic ancestor chain. */
  public function wouldCreateCycle(Block $block, ?Block $parent, ?Collection $blocks = null): bool
  {
    $seen = [];
    while ($parent) {
      if ($parent->id === $block->id || isset($seen[$parent->id])) {
        return true;
      }
      $seen[$parent->id] = true;
      $parent = $blocks === null ? $parent->parent : $blocks->get($parent->parent_id);
    }

    return false;
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
  public function move(Block $block, int $slotTypeId, ?Block $parent, Collection $blocks, Collection $movable, ?Block $replacement = null): bool
  {
    if ($parent && ! $this->parentBelongsToSlot($parent, $block->page_id, $slotTypeId)) {
      return false;
    }
    if ($block->slot_type_id === $slotTypeId && $block->parent_id === $parent?->id) {
      return $replacement === null;
    }
    if ($this->wouldCreateCycle($block, $parent, $blocks)) {
      return false;
    }

    $subtree = $this->movableSubtree($block, $blocks, $movable);
    if ($subtree === null) {
      return false;
    }

    if ($replacement) {
      $outgoing = (clone $block)->fill(['parent_id' => $parent?->id])->setRelation('parent', $parent);
      try {
        app(ContentSourceAuthoring::class)->assertPlacement($outgoing);
      } catch (ContentSourceAuthoringException) {
        return false;
      }

      return $block->slot_type_id === $slotTypeId && $this->movableSubtree($replacement, $blocks, $movable) !== null
        && $this->exchangeSplitChild($block, $parent, $replacement, $blocks, $block->typeSlug());
    }
    if ($this->needsSplitReplacement($block, $blocks) || ! $this->acceptsChild($parent, $block->typeSlug())) {
      return false;
    }

    $order = $this->nextSortOrder($this->siblings($blocks, $slotTypeId, $parent?->id)->max('sort_order'));
    $block->update(['parent_id' => $parent?->id, 'slot_type_id' => $slotTypeId, 'sort_order' => $order]);
    foreach ($subtree->skip(1) as $child) {
      if ($child->slot_type_id !== $slotTypeId) {
        $child->update(['slot_type_id' => $slotTypeId]);
      }
    }

    return true;
  }

  private function movableSubtree(Block $block, Collection $blocks, Collection $movable): ?Collection
  {
    $subtree = collect([$block]);
    $visited = [];
    $children = $blocks->groupBy('parent_id');
    for ($index = 0; $index < $subtree->count(); $index++) {
      $node = $subtree[$index];
      if (isset($visited[$node->id]) || ! $movable->has($node->id)
        || $node->slot_type_id !== $block->slot_type_id) {
        return null;
      }
      $visited[$node->id] = true;
      foreach ($children->get($node->id, []) as $child) {
        $subtree->push($child);
      }
    }

    return $subtree;
  }
}
