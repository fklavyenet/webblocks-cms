<?php

namespace WebBlocks\Cms\Support\Pages;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlotBlock;
use WebBlocks\Cms\Support\Audit\CurrentActorResolver;

class PageOwnedBlockPublisher
{
  public const PUBLISHABLE_STATUSES = [
    'draft',
    'in_review',
  ];

  public function __construct(
    private readonly CurrentActorResolver $currentActorResolver,
    private readonly PageRevisionManager $revisionManager,
  ) {}

  public function summary(Page $page): array
  {
    $page->loadMissing(['slots.slotType', 'slots.sharedSlot']);

    $pageSlots = $page->slots
      ->filter(fn (PageSlot $slot) => $slot->usesPageOwnedBlocks())
      ->values();
    $sharedSlots = $page->slots
      ->filter(fn (PageSlot $slot) => $slot->runtimeSourceType() === PageSlot::SOURCE_TYPE_SHARED_SLOT)
      ->values();

    $included = $this->countsForPageSlots($page, $pageSlots);
    $excluded = $this->countsForSharedSlots($sharedSlots);

    return [
      'total' => $included->sum('total'),
      'by_slot' => $included->values()->all(),
      'shared_slots_excluded' => $excluded->values()->all(),
      'shared_slots_excluded_total' => $excluded->sum('total'),
      'has_unpublished_page_owned_blocks' => $included->sum('total') > 0,
      'has_unpublished_shared_slot_blocks' => $excluded->sum('total') > 0,
    ];
  }

  public function publish(Page $page, ?User $actor = null, ?string $source = null, bool $captureRevision = true): array
  {
    return DB::transaction(function () use ($page, $actor, $source, $captureRevision): array {
      $lockedPage = Page::query()->lockForUpdate()->findOrFail($page->id);
      $before = $this->summary($lockedPage);
      $slotTypeIds = collect($before['by_slot'])->pluck('slot_type_id')->filter()->values();
      $published = collect();

      if ($slotTypeIds->isNotEmpty()) {
        $published = Block::query()
          ->where('page_id', $lockedPage->id)
          ->whereIn('slot_type_id', $slotTypeIds)
          ->whereIn('status', self::PUBLISHABLE_STATUSES)
          ->lockForUpdate()
          ->get();

        $publishedByStatus = $published
          ->groupBy('status')
          ->map(fn (Collection $blocks) => $blocks->count())
          ->all();

        Block::query()
          ->whereKey($published->modelKeys())
          ->update([
            'status' => 'published',
            'updated_at' => now(),
          ]);
      } else {
        $publishedByStatus = [];
      }

      $actorData = $this->currentActorResolver->resolve($actor, $source);
      $lockedPage->forceFill(['updated_by_user_id' => $actorData['user_id']])->save();

      $revision = null;

      if ($captureRevision && $published->isNotEmpty()) {
        $revision = $this->revisionManager->capture(
          $lockedPage->fresh(),
          $actor,
          'Page-owned blocks published',
          'Unpublished page-owned blocks were published without changing the page workflow status.',
          event: 'page_owned_blocks_published',
          source: $source,
        );
      }

      $after = $this->summary($lockedPage->fresh());

      return [
        ...$after,
        'published_count' => $published->count(),
        'published_by_status' => $publishedByStatus,
        'before' => $before,
        'revision_id' => $revision?->id,
      ];
    });
  }

  private function countsForPageSlots(Page $page, Collection $slots): Collection
  {
    if ($slots->isEmpty()) {
      return collect();
    }

    $slotTypeIds = $slots->pluck('slot_type_id')->filter()->values();
    $counts = Block::query()
      ->selectRaw('slot_type_id, status, count(*) as aggregate')
      ->where('page_id', $page->id)
      ->whereIn('slot_type_id', $slotTypeIds)
      ->whereIn('status', self::PUBLISHABLE_STATUSES)
      ->groupBy('slot_type_id', 'status')
      ->get()
      ->groupBy('slot_type_id');

    return $slots
      ->map(function (PageSlot $slot) use ($counts): array {
        $statusCounts = $counts
          ->get($slot->slot_type_id, collect())
          ->mapWithKeys(fn ($row) => [(string) $row->status => (int) $row->aggregate])
          ->all();

        return [
          'slot_id' => $slot->id,
          'slot_type_id' => $slot->slot_type_id,
          'slot' => $slot->slotSlug(),
          'label' => $slot->slotType?->name ?? str($slot->slotSlug())->headline()->toString(),
          'status_counts' => $statusCounts,
          'total' => array_sum($statusCounts),
        ];
      })
      ->filter(fn (array $slot) => $slot['total'] > 0)
      ->values();
  }

  private function countsForSharedSlots(Collection $slots): Collection
  {
    if ($slots->isEmpty()) {
      return collect();
    }

    $sharedSlotIds = $slots->pluck('shared_slot_id')->filter()->values();
    $blockTable = (new Block)->getTable();
    $sharedSlotBlockTable = (new SharedSlotBlock)->getTable();
    $counts = SharedSlotBlock::query()
      ->join($blockTable, $blockTable.'.id', '=', $sharedSlotBlockTable.'.block_id')
      ->selectRaw($sharedSlotBlockTable.'.shared_slot_id, '.$blockTable.'.status, count(*) as aggregate')
      ->whereIn($sharedSlotBlockTable.'.shared_slot_id', $sharedSlotIds)
      ->whereIn($blockTable.'.status', self::PUBLISHABLE_STATUSES)
      ->groupBy($sharedSlotBlockTable.'.shared_slot_id', $blockTable.'.status')
      ->get()
      ->groupBy('shared_slot_id');

    return $slots
      ->map(function (PageSlot $slot) use ($counts): array {
        $statusCounts = $counts
          ->get($slot->shared_slot_id, collect())
          ->mapWithKeys(fn ($row) => [(string) $row->status => (int) $row->aggregate])
          ->all();

        return [
          'slot_id' => $slot->id,
          'slot_type_id' => $slot->slot_type_id,
          'slot' => $slot->slotSlug(),
          'label' => $slot->slotType?->name ?? str($slot->slotSlug())->headline()->toString(),
          'shared_slot_id' => $slot->shared_slot_id,
          'shared_slot_label' => $slot->sharedSlot?->name,
          'status_counts' => $statusCounts,
          'total' => array_sum($statusCounts),
        ];
      })
      ->filter(fn (array $slot) => $slot['total'] > 0)
      ->values();
  }
}
