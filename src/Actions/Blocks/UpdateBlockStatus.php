<?php

namespace WebBlocks\Cms\Actions\Blocks;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotRevisionManager;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;

class UpdateBlockStatus
{
  public function __construct(
    private readonly PageRevisionManager $pageRevisions,
    private readonly SharedSlotRevisionManager $sharedSlotRevisions,
    private readonly SharedSlotSourcePageManager $sharedSlotSourcePages,
  ) {}

  public function execute(Block $block, string $status, Page $page, ?SharedSlot $sharedSlot, User $user): void
  {
    DB::transaction(function () use ($block, $status, $page, $sharedSlot, $user): void {
      $block->forceFill(['status' => $status])->save();

      if ($sharedSlot) {
        $this->sharedSlotSourcePages->rebuildAssignments($sharedSlot);
        $sharedSlot->forceFill(['updated_by_user_id' => $user->id])->save();
        $this->sharedSlotRevisions->capture(
          $sharedSlot->fresh(),
          $user,
          'block_status_updated',
          'Shared Slot block status updated',
          'A Shared Slot block was published or moved to draft.',
        );

        return;
      }

      $page->forceFill(['updated_by_user_id' => $user->id])->save();
      $this->pageRevisions->capture(
        $page->fresh(),
        $user,
        'Block status updated',
        'A page block was published or moved to draft.',
        event: 'block_status_updated',
      );
    });
  }
}
