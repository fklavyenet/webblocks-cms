<?php

namespace WebBlocks\Cms\Actions\Pages;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Support\Contact\ContactConsentRequirements;

final class WritePageSlotSource
{
  public function __construct(private readonly ContactConsentRequirements $consent) {}

  /** The caller validates slot compatibility and write authority. */
  public function execute(Page $page, PageSlot $slot, string $sourceType, ?int $sharedSlotId): void
  {
    DB::transaction(function () use ($page, $slot, $sourceType, $sharedSlotId): void {
      $slot->update([
        'source_type' => $sourceType,
        'shared_slot_id' => $sourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT ? $sharedSlotId : null,
      ]);

      if ($page->status === Page::STATUS_PUBLISHED) {
        $this->consent->assertPage($page->fresh());
      }
    });
  }
}
