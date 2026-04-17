<?php

namespace App\Support\Navigation;

use App\Models\NavigationItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class NavigationTree
{
    public const MAX_DEPTH = 3;

    public function buildMenuTree(string $menuKey): Collection
    {
        $items = NavigationItem::query()
            ->forMenu($menuKey)
            ->with('page')
            ->ordered()
            ->get();

        return $this->nestItems($items);
    }

    public function parentOptions(string $menuKey, ?int $ignoreId = null): Collection
    {
        $items = NavigationItem::query()
            ->forMenu($menuKey)
            ->with('children')
            ->ordered()
            ->get();

        $nested = $this->nestItems($items);

        return $this->flattenOptions($nested, '', $ignoreId);
    }

    public function validateAndNormalizeTreePayload(string $menuKey, array $items): array
    {
        $existing = NavigationItem::query()
            ->forMenu($menuKey)
            ->ordered()
            ->get(['id', 'menu_key']);

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

    private function flattenOptions(Collection $items, string $prefix = '', ?int $ignoreId = null): Collection
    {
        return $items->flatMap(function (NavigationItem $item) use ($prefix, $ignoreId) {
            if ($ignoreId && $item->id === $ignoreId) {
                return collect();
            }

            $label = $prefix === '' ? $item->resolvedTitle() : $prefix.' > '.$item->resolvedTitle();
            $current = collect([['id' => $item->id, 'label' => $label]]);

            return $current->merge($this->flattenOptions($item->children, $label, $ignoreId));
        });
    }
}
