<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use WebBlocks\Cms\Http\Controllers\Admin\PageController;
use WebBlocks\Cms\Http\Controllers\Admin\SharedSlotController;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Tests\TestCase;

class SlotBlockEditorPerformanceTest extends TestCase
{
  #[DataProvider('slotEditorControllers')]
  public function test_flat_slot_blocks_are_hydrated_without_recursive_queries(string $controllerClass): void
  {
    $root = $this->block(10, null, 2);
    $laterChild = $this->block(12, 10, 20);
    $earlierChild = $this->block(11, 10, 10);
    $grandchild = $this->block(13, 11, 10);

    $controller = (new ReflectionClass($controllerClass))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($controllerClass, 'hydrateSlotBlockTree');
    $method->invoke($controller, new Collection([$root, $laterChild, $earlierChild, $grandchild]));

    $this->assertSame([11, 12], $root->children->pluck('id')->all());
    $this->assertSame([13], $earlierChild->children->pluck('id')->all());
    $this->assertSame($root, $earlierChild->parent);
    $this->assertSame($earlierChild, $grandchild->parent);
    $this->assertTrue($laterChild->children->isEmpty());
  }

  public function test_modal_close_updates_the_url_without_reloading_the_editor(): void
  {
    $view = file_get_contents(__DIR__.'/../../resources/views/admin/pages/partials/slot-block-modal.blade.php');
    $script = file_get_contents(__DIR__.'/../../public/cms/js/admin/core.js');

    $this->assertStringContainsString('data-wb-admin-close-history', $view);
    $this->assertStringContainsString("overlay.hasAttribute('data-wb-admin-close-history')", $script);
    $this->assertStringContainsString("window.history.replaceState({}, '', closeUrl)", $script);
  }

  public static function slotEditorControllers(): array
  {
    return [
      'page slots' => [PageController::class],
      'shared slots' => [SharedSlotController::class],
    ];
  }

  private function block(int $id, ?int $parentId, int $sortOrder): Block
  {
    $block = new Block([
      'parent_id' => $parentId,
      'sort_order' => $sortOrder,
    ]);
    $block->id = $id;

    return $block;
  }
}
