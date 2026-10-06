<?php

namespace WebBlocks\Cms\Actions\Blocks;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\Blocks\MobileBlockMedia;

class SetBlockMobileMedia
{
  public function execute(Block $block, ?int $mediaId): void
  {
    $relation = $block->blockMedia()->where('role', MobileBlockMedia::ROLE);

    if ($mediaId === null) {
      $relation->delete();
    } else {
      $relation->updateOrCreate(
        ['role' => MobileBlockMedia::ROLE],
        ['media_id' => $mediaId, 'position' => 0],
      );
    }

    $block->unsetRelation('blockAssets');
    $block->unsetRelation('blockMedia');
  }
}
