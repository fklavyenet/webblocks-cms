<?php

namespace WebBlocks\Cms\Support\Pages;

use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayoutSlot;
use WebBlocks\Cms\Models\PageSlot;

class PageLayoutSlotComparison
{
  public function __construct(
    private readonly PageLayoutManager $pageLayouts,
  ) {}

  public function compare(Page $page): array
  {
    $page->loadMissing('slots.slotType');

    $pageSlots = $page->slots
      ->sortBy(fn (PageSlot $slot) => sprintf('%010d-%010d', (int) $slot->sort_order, (int) $slot->id))
      ->values();
    $layoutSlots = $this->pageLayouts->managedSlotsForHandle($page->publicShellPreset())->values();
    $pageSlotsByKey = $pageSlots->mapWithKeys(function (PageSlot $slot): array {
      $key = $this->pageSlotKey($slot);

      return $key ? [$key => $slot] : [];
    });
    $layoutKeys = $layoutSlots
      ->map(fn (PageLayoutSlot $layoutSlot) => $this->layoutSlotKey($layoutSlot))
      ->filter()
      ->values();

    $layoutRows = $layoutSlots->map(function (PageLayoutSlot $layoutSlot) use ($pageSlotsByKey): array {
      $key = $this->layoutSlotKey($layoutSlot);
      $pageSlot = $key ? $pageSlotsByKey->get($key) : null;

      return [
        'kind' => 'layout',
        'key' => $key,
        'layout_slot' => $layoutSlot,
        'page_slot' => $pageSlot,
        'layout_slot_name' => $key,
        'layout_label' => $this->layoutSlotLabel($layoutSlot),
        'page_slot_name' => $pageSlot?->slotSlug(),
        'page_slot_label' => $pageSlot?->slotType?->name,
        'status' => $pageSlot ? 'present' : 'missing',
        'is_missing' => $pageSlot === null,
        'is_disabled' => $pageSlot?->runtimeSourceType() === PageSlot::SOURCE_TYPE_DISABLED,
        'is_shared_slot' => $pageSlot?->runtimeSourceType() === PageSlot::SOURCE_TYPE_SHARED_SLOT,
        'source_label' => $pageSlot?->sourceTypeLabel(),
        'shared_slot_name' => $this->pageSlotSharedSlotName($pageSlot),
        'sort_order' => (int) ($layoutSlot->sort_order ?? 0),
        'slot_type_id' => (int) ($layoutSlot->slot_type_id ?: $layoutSlot->slotType?->id ?: 0),
      ];
    })->values();

    $extraRows = $pageSlots
      ->filter(function (PageSlot $pageSlot) use ($layoutKeys): bool {
        $key = $this->pageSlotKey($pageSlot);

        return ! $key || ! $layoutKeys->contains($key);
      })
      ->map(function (PageSlot $pageSlot): array {
        return [
          'kind' => 'extra',
          'key' => $this->pageSlotKey($pageSlot),
          'layout_slot' => null,
          'page_slot' => $pageSlot,
          'layout_slot_name' => null,
          'layout_label' => null,
          'page_slot_name' => $pageSlot->slotSlug(),
          'page_slot_label' => $pageSlot->slotType?->name,
          'status' => 'extra',
          'is_missing' => false,
          'is_disabled' => $pageSlot->runtimeSourceType() === PageSlot::SOURCE_TYPE_DISABLED,
          'is_shared_slot' => $pageSlot->runtimeSourceType() === PageSlot::SOURCE_TYPE_SHARED_SLOT,
          'source_label' => $pageSlot->sourceTypeLabel(),
          'shared_slot_name' => $this->pageSlotSharedSlotName($pageSlot),
          'sort_order' => (int) $pageSlot->sort_order,
          'slot_type_id' => (int) $pageSlot->slot_type_id,
        ];
      })
      ->values();

    $missingSlots = $layoutRows
      ->filter(fn (array $row) => $row['is_missing'] && $row['slot_type_id'] > 0)
      ->values();

    return [
      'layout_handle' => $page->publicShellPreset(),
      'layout_label' => $this->pageLayouts->labelForHandle($page->publicShellPreset()),
      'layout_slots' => $layoutRows,
      'extra_slots' => $extraRows,
      'layout_slot_count' => $layoutRows->count(),
      'page_slot_count' => $pageSlots->count(),
      'present_count' => $layoutRows->where('status', 'present')->count(),
      'missing_count' => $missingSlots->count(),
      'extra_count' => $extraRows->count(),
      'disabled_count' => $pageSlots->filter(fn (PageSlot $slot) => $slot->runtimeSourceType() === PageSlot::SOURCE_TYPE_DISABLED)->count(),
      'shared_slot_count' => $pageSlots->filter(fn (PageSlot $slot) => $slot->runtimeSourceType() === PageSlot::SOURCE_TYPE_SHARED_SLOT)->count(),
      'missing_slots' => $missingSlots,
      'has_layout_slots' => $layoutRows->isNotEmpty(),
      'all_layout_slots_present' => $missingSlots->isEmpty(),
    ];
  }

  private function layoutSlotKey(PageLayoutSlot $layoutSlot): ?string
  {
    return LayoutMarkup::normalizeSlotName($layoutSlot->slot_name)
      ?? LayoutMarkup::normalizeSlotName($layoutSlot->slotType?->slug);
  }

  private function pageSlotKey(PageSlot $pageSlot): ?string
  {
    return LayoutMarkup::normalizeSlotName($pageSlot->slotSlug());
  }

  private function layoutSlotLabel(PageLayoutSlot $layoutSlot): string
  {
    return trim((string) ($layoutSlot->label ?: $layoutSlot->slotType?->name ?: str($layoutSlot->slot_name ?: 'slot')->headline()->toString()));
  }

  private function pageSlotSharedSlotName(?PageSlot $pageSlot): ?string
  {
    if (! $pageSlot || $pageSlot->runtimeSourceType() !== PageSlot::SOURCE_TYPE_SHARED_SLOT) {
      return null;
    }

    if ($pageSlot->relationLoaded('sharedSlot')) {
      return $pageSlot->sharedSlot?->name;
    }

    return null;
  }
}
