<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\Blocks\BlockDeletionManager;
use WebBlocks\Cms\Tests\TestCase;

class BlockDeletionScopeReuseTest extends TestCase
{
  #[Test]
  public function a_preloaded_tree_preserves_postorder_and_cannot_cross_its_page_or_slot(): void
  {
    $make = function (int $id, ?int $parent, int $page = 1, int $slot = 2): Block {
      $block = new Block;
      $block->setRawAttributes(['id' => $id, 'parent_id' => $parent, 'page_id' => $page, 'slot_type_id' => $slot]);

      return $block;
    };
    $root = $make(10, null);
    $scope = collect([$root, $make(11, 10), $make(12, 11), $make(13, 10, 99), $make(14, 10, 1, 99), $make(15, null)]);
    DB::enableQueryLog();
    $ordered = app(BlockDeletionManager::class)->recursiveDeleteOrder($root, $scope);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    $this->assertSame([12, 11, 10], $ordered->pluck('id')->all());
    $this->assertSame([], $queries, 'A caller-supplied tree must not be fetched again.');
  }
}
