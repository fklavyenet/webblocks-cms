<?php

namespace WebBlocks\Cms\Actions\Pages;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Support\Contact\ContactConsentRequirements;

final class WritePageSlotSource
{
  public function __construct(private readonly ContactConsentRequirements $consent) {}

  /** The caller validates slot compatibility and write authority. */
  public function execute(Page $page, PageSlot $slot, string $sourceType, ?int $sharedSlotId): void
  {
    if ($sourceType === PageSlot::SOURCE_TYPE_DISABLED && $slot->isProtectedMain($page)) {
      throw ValidationException::withMessages(['source_type' => __('webblocks-cms::admin.page_layout_picker.main_protected')]);
    }

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
