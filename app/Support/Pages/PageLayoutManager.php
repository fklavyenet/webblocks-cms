<?php

namespace App\Support\Pages;

use App\Models\Page;
use App\Models\PageLayout;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PageLayoutManager
{
    public function tableExists(): bool
    {
        return Schema::hasTable('page_layouts');
    }

    public function activeHandles(): array
    {
        if (! $this->tableExists()) {
            return PageLayoutCatalog::handles();
        }

        return PageLayout::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('handle')
            ->all();
    }

    public function findByHandle(?string $handle, bool $includeInactive = true): ?PageLayout
    {
        $normalized = Page::normalizePublicShellHandle($handle);

        if (! $this->tableExists()) {
            return null;
        }

        return PageLayout::query()
            ->when(! $includeInactive, fn ($query) => $query->where('is_active', true))
            ->where('handle', $normalized)
            ->first();
    }

    public function resolveShellType(?string $handle): string
    {
        $normalized = Page::normalizePublicShellHandle($handle);
        $layout = $this->findByHandle($normalized);

        if ($layout) {
            return Page::normalizePublicShellType($layout->shell_type);
        }

        $fallback = PageLayoutCatalog::fallback($normalized);

        if ($fallback) {
            return Page::normalizePublicShellType($fallback['shell_type'] ?? 'default');
        }

        return Page::normalizePublicShellType($normalized);
    }

    public function labelForHandle(?string $handle): string
    {
        $normalized = Page::normalizePublicShellHandle($handle);
        $layout = $this->findByHandle($normalized);

        if ($layout) {
            return $layout->name;
        }

        $fallback = PageLayoutCatalog::fallback($normalized);

        if ($fallback) {
            return $fallback['name'];
        }

        return 'Legacy Layout ('.$normalized.')';
    }

    public function pageSelectionOptions(?string $currentHandle = null): array
    {
        $currentHandle = Page::normalizePublicShellHandle($currentHandle);
        $layouts = $this->ordered(activeOnly: true)->map(fn (array $layout) => [
            'value' => $layout['handle'],
            'label' => $layout['name'],
            'description' => $layout['description'],
            'inactive' => false,
            'legacy' => false,
        ])->values();

        if ($currentHandle === '') {
            return $layouts->all();
        }

        if ($layouts->contains(fn (array $option) => $option['value'] === $currentHandle)) {
            return $layouts->all();
        }

        $matchedLayout = $this->layoutArray($currentHandle, includeInactive: true);

        if ($matchedLayout) {
            $layouts->push([
                'value' => $matchedLayout['handle'],
                'label' => $matchedLayout['name'].(! ($matchedLayout['is_active'] ?? true) ? ' (inactive)' : ''),
                'description' => $matchedLayout['description'] ?? null,
                'inactive' => ! ($matchedLayout['is_active'] ?? true),
                'legacy' => false,
            ]);

            return $layouts->all();
        }

        $layouts->push([
            'value' => $currentHandle,
            'label' => 'Current Legacy Layout ('.$currentHandle.')',
            'description' => 'This page still uses a layout handle that is not defined on this install.',
            'inactive' => false,
            'legacy' => true,
        ]);

        return $layouts->all();
    }

    public function sharedSlotSelectionOptions(?string $currentHandle = null): array
    {
        return $this->pageSelectionOptions($currentHandle);
    }

    public function ordered(bool $activeOnly = false, bool $includeInactiveCurrent = false): Collection
    {
        if (! $this->tableExists()) {
            return collect(PageLayoutCatalog::definitions())
                ->when($activeOnly, fn (Collection $collection) => $collection->where('is_active', true))
                ->sortBy(fn (array $layout) => sprintf('%010d-%s', (int) ($layout['sort_order'] ?? 0), $layout['name'] ?? ''))
                ->values();
        }

        return PageLayout::query()
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (PageLayout $layout) => [
                'id' => $layout->id,
                'handle' => $layout->handle,
                'name' => $layout->name,
                'description' => $layout->description,
                'is_system' => $layout->is_system,
                'is_active' => $layout->is_active,
                'sort_order' => $layout->sort_order,
                'shell_type' => $layout->shell_type,
                'slot_schema' => $layout->slot_schema,
                'wrapper_schema' => $layout->wrapper_schema,
            ])
            ->values();
    }

    private function layoutArray(string $handle, bool $includeInactive = true): ?array
    {
        $layout = $this->findByHandle($handle, $includeInactive);

        if ($layout) {
            return [
                'id' => $layout->id,
                'handle' => $layout->handle,
                'name' => $layout->name,
                'description' => $layout->description,
                'is_system' => $layout->is_system,
                'is_active' => $layout->is_active,
                'sort_order' => $layout->sort_order,
                'shell_type' => $layout->shell_type,
                'slot_schema' => $layout->slot_schema,
                'wrapper_schema' => $layout->wrapper_schema,
            ];
        }

        return PageLayoutCatalog::fallback($handle);
    }
}
