<?php

namespace WebBlocks\Cms\Support\Navigation;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Site;

class NavigationTree
{
  public const MAX_DEPTH = 3;

  public function buildMenuTree(string $menuKey, Site|int|null $site = null): Collection
  {
    $items = NavigationItem::query()
      ->forSite($site)
      ->forMenu($menuKey)
      ->with(['page.translations'])
      ->ordered()
      ->get();

    return $this->nestItems($items);
  }

  public function parentOptions(string $menuKey, Site|int|null $site = null, ?int $ignoreId = null): Collection
  {
    $items = NavigationItem::query()
      ->forSite($site)
      ->forMenu($menuKey)
      ->with(['children', 'page.translations'])
      ->ordered()
      ->get();

    $nested = $this->nestItems($items);
    $ignoredIds = $ignoreId ? $this->ignoredParentIds($nested, $ignoreId) : [];

    return $this->flattenOptions($nested, '', $ignoredIds);
  }

  public function validateAndNormalizeTreePayload(string $menuKey, Site|int|null $site, array $items): array
  {
    $existing = NavigationItem::query()
      ->forSite($site)
      ->forMenu($menuKey)
      ->ordered()
      ->get(['id', 'menu_key', 'link_type']);

    $expectedIds = $existing->pluck('id')->map(fn ($id) => (int) $id)->all();
    $payloadIds = collect($items)->pluck('id')->map(fn ($id) => (int) $id)->all();

    sort($expectedIds);
    sort($payloadIds);

    if ($expectedIds !== $payloadIds) {
      throw ValidationException::withMessages([
        'items' => 'Reorder payload must include every item in the selected menu exactly once.',
      ]);
    }

    $nodes = [];

    foreach ($items as $index => $item) {
      $id = (int) ($item['id'] ?? 0);
      $parentId = isset($item['parent_id']) && $item['parent_id'] !== '' ? (int) $item['parent_id'] : null;
      $position = is_numeric($item['position'] ?? null) ? (int) $item['position'] : ($index + 1);

      if ($id <= 0) {
        throw ValidationException::withMessages([
          'items' => 'Each reordered item must include a valid id.',
        ]);
      }

      if ($parentId === $id) {
        throw ValidationException::withMessages([
          'items' => 'An item cannot be nested under itself.',
        ]);
      }

      $nodes[$id] = [
        'id' => $id,
        'parent_id' => $parentId,
        'position' => max(1, $position),
      ];
    }

    foreach ($nodes as $id => $node) {
      $parentId = $node['parent_id'];

      if ($parentId !== null && ! isset($nodes[$parentId])) {
        throw ValidationException::withMessages([
          'items' => 'Parent items must belong to the selected menu.',
        ]);
      }

      if ($parentId !== null) {
        $parentItem = $existing->firstWhere('id', $parentId);

        if ($parentItem && $parentItem->link_type !== NavigationItem::LINK_GROUP) {
          throw ValidationException::withMessages([
            'items' => 'Only navigation groups can contain child items.',
          ]);
        }
      }

      $depth = 1;
      $cursor = $parentId;
      $visited = [$id];

      while ($cursor !== null) {
        if (in_array($cursor, $visited, true)) {
          throw ValidationException::withMessages([
            'items' => 'Navigation cycles are not allowed.',
          ]);
        }

        $visited[] = $cursor;
        $depth++;

        if ($depth > self::MAX_DEPTH) {
          throw ValidationException::withMessages([
            'items' => 'Navigation depth cannot exceed 3 levels.',
          ]);
        }

        $cursor = $nodes[$cursor]['parent_id'] ?? null;
      }
    }

    return collect($nodes)
      ->sortBy(['parent_id', 'position', 'id'])
      ->values()
      ->all();
  }

  private function nestItems(Collection $items, ?int $parentId = null, int $depth = 1): Collection
  {
    return $items
      ->where('parent_id', $parentId)
      ->sortBy('position')
      ->values()
      ->map(function (NavigationItem $item) use ($items, $depth) {
        $item->setRelation('children', $depth < self::MAX_DEPTH
          ? $this->nestItems($items, $item->id, $depth + 1)
          : collect());

        return $item;
      });
  }

  private function flattenOptions(Collection $items, string $prefix = '', array $ignoredIds = []): Collection
  {
    return $items->flatMap(function (NavigationItem $item) use ($prefix, $ignoredIds) {
      if (in_array($item->id, $ignoredIds, true)) {
        return collect();
      }

      $label = $prefix === '' ? $item->resolvedTitle() : $prefix.' > '.$item->resolvedTitle();
      $current = $item->link_type === NavigationItem::LINK_GROUP
        ? collect([['id' => $item->id, 'label' => $label]])
        : collect();

      return $current->merge($this->flattenOptions($item->children, $label, $ignoredIds));
    });
  }

  private function ignoredParentIds(Collection $items, int $ignoreId): array
  {
    $ignored = [];

    $collect = function (Collection $nodes) use (&$collect, &$ignored, $ignoreId): bool {
      foreach ($nodes as $node) {
        if ($node->id === $ignoreId) {
          $ignored[] = $node->id;
          $this->collectDescendantIds($node->children, $ignored);

          return true;
        }

        if ($collect($node->children)) {
          return true;
        }
      }

      return false;
    };

    $collect($items);

    return $ignored;
  }

  private function collectDescendantIds(Collection $items, array &$ignored): void
  {
    foreach ($items as $item) {
      $ignored[] = $item->id;
      $this->collectDescendantIds($item->children, $ignored);
    }
  }
}
