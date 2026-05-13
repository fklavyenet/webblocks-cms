<?php

namespace App\Support\Pages;

use App\Models\Page;
use App\Models\PageLayout;
use App\Models\PageLayoutSlot;
use App\Models\SlotType;
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
            ->when($this->slotsTableExists(), fn ($query) => $query->with(['layoutSlots.slotType']))
            ->when(! $includeInactive, fn ($query) => $query->where('is_active', true))
            ->where('handle', $normalized)
            ->first();
    }

    public function slotsTableExists(): bool
    {
        return Schema::hasTable('page_layout_slots');
    }

    public function resolveShellType(?string $handle): string
    {
        $normalized = Page::normalizePublicShellHandle($handle);
        $layout = $this->findByHandle($normalized);

        if ($layout) {
            $managedShellType = $this->inferManagedShellType($this->managedSlotsForHandle($normalized));

            if ($managedShellType !== null) {
                return $managedShellType;
            }

            return Page::normalizePublicShellType($layout->shell_type);
        }

        $fallback = PageLayoutCatalog::fallback($normalized);

        if ($fallback) {
            return Page::normalizePublicShellType($fallback['shell_type'] ?? 'default');
        }

        return Page::normalizePublicShellType($normalized);
    }

    private function inferManagedShellType(Collection $slots): ?string
    {
        if ($slots->isEmpty()) {
            return null;
        }

        $hasDocsSidebar = $slots->contains(function (PageLayoutSlot $slot) {
            $slotName = LayoutMarkup::normalizeSlotName($slot->slot_name) ?? LayoutMarkup::normalizeSlotName($slot->slotType?->slug);
            $classes = LayoutMarkup::normalizeTokenList($slot->html_classes);

            return $slotName === 'sidebar'
                && ($slot->html_id === 'docsSidebar' || str_contains((string) $classes, 'wb-sidebar'));
        });

        $hasDocsMain = $slots->contains(function (PageLayoutSlot $slot) {
            $slotName = LayoutMarkup::normalizeSlotName($slot->slot_name) ?? LayoutMarkup::normalizeSlotName($slot->slotType?->slug);
            $classes = LayoutMarkup::normalizeTokenList($slot->html_classes);

            return $slotName === 'main'
                && str_contains((string) $classes, 'wb-dashboard-main');
        });

        return ($hasDocsSidebar || $hasDocsMain) ? 'docs' : 'default';
    }

    public function bodyClassForHandle(?string $handle): ?string
    {
        $normalized = Page::normalizePublicShellHandle($handle);
        $layout = $this->findByHandle($normalized);

        if ($layout) {
            return LayoutMarkup::normalizeTokenList($layout->body_class);
        }

        return LayoutMarkup::normalizeTokenList(PageLayoutCatalog::fallback($normalized)['body_class'] ?? null);
    }

    public function managedSlotsForHandle(?string $handle, bool $includeInactive = false): Collection
    {
        $normalized = Page::normalizePublicShellHandle($handle);
        $layout = $this->findByHandle($normalized, includeInactive: true);

        if ($layout && $this->slotsTableExists()) {
            $slots = $layout->relationLoaded('layoutSlots')
                ? $layout->layoutSlots
                : $layout->layoutSlots()->with('slotType')->get();

            $resolvedSlots = $slots
                ->when(! $includeInactive, fn (Collection $collection) => $collection->where('is_active', true))
                ->sortBy(fn (PageLayoutSlot $slot) => sprintf('%010d-%s', (int) $slot->sort_order, $slot->slot_name))
                ->values();

            if ($resolvedSlots->isNotEmpty()) {
                return $resolvedSlots;
            }
        }

        $fallback = PageLayoutCatalog::fallback($normalized);

        $managedSlots = $fallback
            ? $this->managedSlotsFromDefinitions($fallback['managed_slots'] ?? [], $includeInactive)
            : collect();

        if ($managedSlots->isNotEmpty()) {
            return $managedSlots;
        }

        if ($layout) {
            $legacySlots = $this->syntheticSlotsFromSlugs($this->legacySlotSchemaForLayout($layout));

            if ($legacySlots->isNotEmpty()) {
                return $legacySlots;
            }
        }

        if (! $fallback) {
            return collect();
        }

        return $this->syntheticSlotsFromSlugs(array_values(array_filter($fallback['slot_schema'] ?? [])));
    }

    public function slotDefinitionForPageSlot(Page $page, string $slotSlug): ?PageLayoutSlot
    {
        $normalizedSlug = LayoutMarkup::normalizeSlotName($slotSlug);

        if (! $normalizedSlug) {
            return null;
        }

        return $this->managedSlotsForHandle($page->publicShellPreset())
            ->first(function (PageLayoutSlot $slot) use ($normalizedSlug) {
                return in_array($normalizedSlug, array_filter([
                    LayoutMarkup::normalizeSlotName($slot->slot_name),
                    LayoutMarkup::normalizeSlotName($slot->slotType?->slug),
                ]), true);
            });
    }

    public function orderedSlotSlugsForHandle(?string $handle): array
    {
        $managed = $this->managedSlotsForHandle($handle)
            ->map(fn (PageLayoutSlot $slot) => LayoutMarkup::normalizeSlotName($slot->slot_name) ?? LayoutMarkup::normalizeSlotName($slot->slotType?->slug))
            ->filter()
            ->values();

        if ($managed->isNotEmpty()) {
            return $managed->all();
        }

        $layout = $this->findByHandle(Page::normalizePublicShellHandle($handle), includeInactive: true);

        if ($layout) {
            return $this->legacySlotSchemaForLayout($layout);
        }

        return array_values(array_filter(PageLayoutCatalog::fallback(Page::normalizePublicShellHandle($handle))['slot_schema'] ?? []));
    }

    private function syntheticSlotsFromSlugs(array $slotSlugs): Collection
    {
        return collect($slotSlugs)
            ->values()
            ->map(function (mixed $slotSlug, int $index) {
                $normalizedSlotSlug = LayoutMarkup::normalizeSlotName($slotSlug);

                if (! $normalizedSlotSlug) {
                    return null;
                }

                $slotType = SlotType::query()->where('slug', $normalizedSlotSlug)->first();
                $layoutSlot = new PageLayoutSlot([
                    'slot_name' => $normalizedSlotSlug,
                    'label' => $slotType?->name ?? str($normalizedSlotSlug)->headline()->toString(),
                    'is_required' => false,
                    'is_active' => true,
                    'is_system' => true,
                    'sort_order' => $index,
                ]);

                if ($slotType) {
                    $layoutSlot->slot_type_id = $slotType->id;
                    $layoutSlot->setRelation('slotType', $slotType);
                }

                return $layoutSlot;
            })
            ->filter()
            ->values();
    }

    private function legacySlotSchemaForLayout(PageLayout $layout): array
    {
        return array_values(array_filter(is_array($layout->slot_schema) ? $layout->slot_schema : []));
    }

    private function managedSlotsFromDefinitions(array $definitions, bool $includeInactive): Collection
    {
        return collect($definitions)
            ->filter(fn (array $slot) => $includeInactive || ($slot['is_active'] ?? true))
            ->map(function (array $slot) {
                $slotTypeSlug = $slot['slot_type_slug'] ?? $slot['slot_name'];
                $layoutSlot = new PageLayoutSlot([
                    'slot_name' => $slot['slot_name'],
                    'label' => $slot['label'] ?? null,
                    'description' => $slot['description'] ?? null,
                    'html_element' => $slot['html_element'] ?? 'div',
                    'html_id' => $slot['html_id'] ?? null,
                    'html_classes' => $slot['html_classes'] ?? null,
                    'before_html' => $slot['before_html'] ?? null,
                    'start_html' => $slot['start_html'] ?? null,
                    'end_html' => $slot['end_html'] ?? null,
                    'after_html' => $slot['after_html'] ?? null,
                    'is_required' => (bool) ($slot['is_required'] ?? false),
                    'is_active' => (bool) ($slot['is_active'] ?? true),
                    'is_system' => (bool) ($slot['is_system'] ?? true),
                    'sort_order' => (int) ($slot['sort_order'] ?? 0),
                ]);

                $layoutSlot->setRelation('slotType', SlotType::query()->where('slug', $slotTypeSlug)->first()
                    ?? new SlotType([
                        'slug' => $slotTypeSlug,
                        'name' => $slot['label'] ?? str($slot['slot_name'] ?? 'main')->headline()->toString(),
                    ]));

                return $layoutSlot;
            })
            ->values();
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
                'body_class' => $layout->body_class,
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
                'body_class' => $layout->body_class,
                'shell_type' => $layout->shell_type,
                'slot_schema' => $layout->slot_schema,
                'wrapper_schema' => $layout->wrapper_schema,
            ];
        }

        return PageLayoutCatalog::fallback($handle);
    }
}
