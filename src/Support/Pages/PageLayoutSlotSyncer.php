<?php

namespace WebBlocks\Cms\Support\Pages;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Audit\CurrentActorResolver;

class PageLayoutSlotSyncer
{
  public function __construct(
    private readonly PageLayoutManager $pageLayouts,
    private readonly PageLayoutSlotComparison $comparison,
    private readonly PageRevisionManager $revisionManager,
    private readonly CurrentActorResolver $currentActorResolver,
  ) {}

  public function seedInitialSlots(Page $page, string $layoutHandle, bool $requirePersistence = false, array $requested = []): int
  {
    $layoutSlots = $this->pageLayouts->managedSlotsForHandle($layoutHandle)->filter(function ($slot) use ($layoutHandle, $requested) {
      $slug = $slot->slotType?->slug ?? $slot->slot_name;

      return PageLayoutCatalog::allowsSlot($layoutHandle, $slug)
        && (PageLayoutCatalog::autoAddsSlot($layoutHandle, $slug) || in_array($slug, $requested, true));
    })->values();

    if ($layoutSlots->isEmpty()) {
      return 0;
    }

    $existingSlotTypeIds = $page->slots()->pluck('slot_type_id')->map(fn ($value) => (int) $value)->all();
    $hadExistingSlots = $existingSlotTypeIds !== [];
    $nextSortOrder = $hadExistingSlots
      ? ((int) $page->slots()->max('sort_order') + 1)
      : 0;
    $created = 0;

    foreach ($layoutSlots as $layoutSlot) {
      $slotTypeId = (int) ($layoutSlot->slot_type_id ?: $layoutSlot->slotType?->id ?: 0);

      if ($slotTypeId <= 0 || in_array($slotTypeId, $existingSlotTypeIds, true)) {
        continue;
      }

      $slot = $page->slots()->create($this->newPageSlotAttributes(
        $slotTypeId,
        $hadExistingSlots ? $nextSortOrder++ : max((int) ($layoutSlot->sort_order ?? 0), 0),
      ));
      if ($requirePersistence && ! $slot->exists) {
        throw new RuntimeException('A page slot model event cancelled draft creation.');
      }

      $existingSlotTypeIds[] = $slotTypeId;
      $created++;
    }

    return $created;
  }

  public function changeLayout(Page $page, string $target): void
  {
    $removed = $this->slotsRemovedForLayoutChange($page, $target);
    if ($removed !== []) {
      $page->slots()->whereKey($removed)->delete();
      $page->unsetRelation('slots');
    }
  }

  public function slotsRemovedForLayoutChange(Page $page, string $target): array
  {
    if ($page->publicShellPreset() === $target) {
      return [];
    }

    $remove = [];
    foreach ($page->slots()->with(['slotType', 'sharedSlot'])->get() as $slot) {
      $slug = $slot->slotSlug();
      $losesPlacement = ! PageLayoutCatalog::allowsSlot($target, $slug);
      $shared = $slot->sharedSlot;
      $sharedLayout = trim((string) $shared?->public_shell);
      $losesSharedSlot = $sharedLayout !== '' && Page::normalizePublicShellHandle($sharedLayout) !== $target
        && $shared->isCompatibleWithPageSlot($page, $slug);

      if ($losesSharedSlot || ($losesPlacement && $this->hasContentOrBinding($page, $slot))) {
        throw ValidationException::withMessages([
          'public_shell' => __('webblocks-cms::admin.page_layout_picker.incompatible_slot', ['slot' => $slot->slotType?->name ?? $slug]),
        ]);
      }

      if ($losesPlacement) {
        $remove[] = $slot->id;
      }
    }

    return $remove;
  }

  public function applyColumnSidebars(Page $page, array $selected): void
  {
    $types = SlotType::query()->whereIn('slug', PageLayoutCatalog::COLUMN_SIDEBARS)->where('status', 'published')->pluck('slug', 'id');
    foreach ($page->slots()->whereIn('slot_type_id', $types->keys())->get() as $slot) {
      if (in_array($types[$slot->slot_type_id], $selected, true)) {
        continue;
      }

      $this->assertSidebarRemovable($page, $slot);
      $slot->delete();
    }

    $existing = $page->slots()->pluck('slot_type_id');
    $order = (int) $page->slots()->max('sort_order') + 1;
    foreach ($types as $typeId => $slug) {
      if (in_array($slug, $selected, true) && ! $existing->contains($typeId)) {
        $page->slots()->create($this->newPageSlotAttributes($typeId, $order++));
      }
    }

    $page->unsetRelation('slots');
  }

  public function assertSidebarRemovable(Page $page, PageSlot $slot): void
  {
    if ($this->hasContentOrBinding($page, $slot)) {
      throw ValidationException::withMessages([
        'column_sidebars' => __('webblocks-cms::admin.page_layout_picker.incompatible_slot', ['slot' => $slot->slotType?->name]),
      ]);
    }
  }

  private function hasContentOrBinding(Page $page, PageSlot $slot): bool
  {
    return $slot->runtimeSourceType() === PageSlot::SOURCE_TYPE_SHARED_SLOT
      || $page->blocks()->where('slot_type_id', $slot->slot_type_id)->exists();
  }

  public function syncMissingSlots(Page $page, User $user): array
  {
    return DB::transaction(function () use ($page, $user): array {
      $page = Page::query()->lockForUpdate()->findOrFail($page->id);

      $lockedSlots = $page->slots()
        ->orderBy('sort_order')
        ->orderBy('id')
        ->lockForUpdate()
        ->get();

      $page->setRelation('slots', $lockedSlots);

      $comparison = $this->comparison->compare($page);
      $missingSlots = $comparison['missing_slots'];

      if ($missingSlots->isEmpty()) {
        return [
          'added_count' => 0,
          'added_slots' => collect(),
          'noop' => true,
        ];
      }

      $nextSortOrder = $lockedSlots->isEmpty()
        ? 0
        : ((int) $lockedSlots->max('sort_order') + 1);
      $addedSlots = collect();

      foreach ($missingSlots as $missingSlot) {
        $slotTypeId = (int) ($missingSlot['slot_type_id'] ?? 0);

        if ($slotTypeId <= 0) {
          continue;
        }

        $created = $page->slots()->create($this->newPageSlotAttributes($slotTypeId, $nextSortOrder++));
        $addedSlots->push([
          'id' => $created->id,
          'slot_name' => $missingSlot['layout_slot_name'],
          'label' => $missingSlot['layout_label'],
        ]);
      }

      if ($addedSlots->isEmpty()) {
        return [
          'added_count' => 0,
          'added_slots' => collect(),
          'noop' => true,
        ];
      }

      $actor = $this->currentActorResolver->resolve($user);

      $page->forceFill([
        'updated_by_user_id' => $actor['user_id'],
      ])->save();

      $this->revisionManager->capture(
        $page->fresh(),
        $user,
        'Layout slots synced',
        'Missing Page Layout slots were added without removing existing page slots.',
        event: 'slot_changed',
      );

      return [
        'added_count' => $addedSlots->count(),
        'added_slots' => $addedSlots,
        'noop' => false,
      ];
    });
  }

  private function newPageSlotAttributes(int $slotTypeId, int $sortOrder): array
  {
    $attributes = [
      'slot_type_id' => $slotTypeId,
      'sort_order' => $sortOrder,
    ];

    if (Schema::hasColumn('wbcms_page_slots', 'source_type')) {
      $attributes['source_type'] = PageSlot::SOURCE_TYPE_PAGE;
    }

    if (Schema::hasColumn('wbcms_page_slots', 'shared_slot_id')) {
      $attributes['shared_slot_id'] = null;
    }

    return $attributes;
  }
}
