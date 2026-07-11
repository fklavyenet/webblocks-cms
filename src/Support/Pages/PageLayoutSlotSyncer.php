<?php

namespace WebBlocks\Cms\Support\Pages;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Support\Audit\CurrentActorResolver;

class PageLayoutSlotSyncer
{
  public function __construct(
    private readonly PageLayoutManager $pageLayouts,
    private readonly PageLayoutSlotComparison $comparison,
    private readonly PageRevisionManager $revisionManager,
    private readonly CurrentActorResolver $currentActorResolver,
  ) {}

  public function seedInitialSlots(Page $page, string $layoutHandle): int
  {
    $layoutSlots = $this->pageLayouts->managedSlotsForHandle($layoutHandle)->values();

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

      $page->slots()->create($this->newPageSlotAttributes(
        $slotTypeId,
        $hadExistingSlots ? $nextSortOrder++ : max((int) ($layoutSlot->sort_order ?? 0), 0),
      ));

      $existingSlotTypeIds[] = $slotTypeId;
      $created++;
    }

    return $created;
  }

  public function syncMissingSlots(Page $page, User $user): array
  {
    return DB::transaction(function () use ($page, $user): array {
      $page->loadMissing('slots.slotType');

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
