<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use WebBlocks\Cms\Http\Controllers\Admin\BlockController;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Columns, Feature Grid and Link List author their children from a repeater on
 * the parent's own form. A row the sync step cannot use is skipped — and the
 * stale sweep used to read "skipped" as "removed by the editor" and delete the
 * block behind it.
 */
class BuilderChildItemRetentionTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function a_row_the_sync_step_cannot_use_keeps_its_block(): void
  {
    [$page, $slotType] = $this->seedPage();
    [$columns, $itemType] = $this->seedColumns($page, $slotType);

    $item = $this->seedColumnItem($page, $slotType, $columns, $itemType, 'Has a title', null);

    $this->syncColumnItems($columns, [
      $this->row($item->id, $itemType->id, 'Has a title', null),
    ]);

    $this->assertNotNull(Block::query()->find($item->id), 'A skipped row must not delete the block it stands for.');
  }

  #[Test]
  public function an_unresolvable_item_block_type_does_not_wipe_every_child(): void
  {
    [$page, $slotType] = $this->seedPage();
    [$columns, $itemType] = $this->seedColumns($page, $slotType);

    $first = $this->seedColumnItem($page, $slotType, $columns, $itemType, 'One', 'First column');
    $second = $this->seedColumnItem($page, $slotType, $columns, $itemType, 'Two', 'Second column');

    // What the form posts when the column_item catalog row is unpublished: the
    // hidden block_type_id is empty on every row, so no row survives the guard.
    $this->syncColumnItems($columns, [
      $this->row($first->id, null, 'One', 'First column'),
      $this->row($second->id, null, 'Two', 'Second column'),
    ]);

    $this->assertSame(2, Block::query()->where('parent_id', $columns->id)->count());
  }

  #[Test]
  public function a_row_marked_for_deletion_is_still_deleted(): void
  {
    [$page, $slotType] = $this->seedPage();
    [$columns, $itemType] = $this->seedColumns($page, $slotType);

    $kept = $this->seedColumnItem($page, $slotType, $columns, $itemType, 'Keep', 'Kept column');
    $dropped = $this->seedColumnItem($page, $slotType, $columns, $itemType, 'Drop', 'Dropped column');

    $this->syncColumnItems($columns, [
      $this->row($kept->id, $itemType->id, 'Keep', 'Kept column'),
      array_merge($this->row($dropped->id, $itemType->id, 'Drop', 'Dropped column'), ['_delete' => true]),
    ]);

    $this->assertNotNull(Block::query()->find($kept->id));
    $this->assertNull(Block::query()->find($dropped->id));
  }

  #[Test]
  public function a_child_the_editor_removed_from_the_list_is_deleted(): void
  {
    [$page, $slotType] = $this->seedPage();
    [$columns, $itemType] = $this->seedColumns($page, $slotType);

    $kept = $this->seedColumnItem($page, $slotType, $columns, $itemType, 'Keep', 'Kept column');
    $gone = $this->seedColumnItem($page, $slotType, $columns, $itemType, 'Gone', 'Removed column');

    $this->syncColumnItems($columns, [
      $this->row($kept->id, $itemType->id, 'Keep', 'Kept column'),
    ]);

    $this->assertNotNull(Block::query()->find($kept->id));
    $this->assertNull(Block::query()->find($gone->id), 'A row no longer posted back is still a removal.');
  }

  /**
   * @param  array<int, array<string, mixed>>  $items
   */
  private function syncColumnItems(Block $columns, array $items): void
  {
    $sync = new ReflectionMethod(BlockController::class, 'syncColumnItems');
    $sync->invoke(app(BlockController::class), $columns->fresh(['children']), $items, null);
  }

  /**
   * @return array<string, mixed>
   */
  private function row(?int $id, ?int $blockTypeId, ?string $title, ?string $content): array
  {
    return [
      'id' => $id,
      'block_type_id' => $blockTypeId,
      'title' => $title,
      'subtitle' => null,
      'content' => $content,
      'url' => null,
      'eyebrow' => null,
      'settings' => null,
      'status' => 'published',
      'is_system' => false,
      'sort_order' => 0,
      '_delete' => false,
    ];
  }

  /**
   * @return array{0: Page, 1: SlotType}
   */
  private function seedPage(): array
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $slotType = SlotType::query()->create(['name' => 'Main', 'slug' => 'main', 'status' => 'published', 'sort_order' => 0]);
    $page = Page::query()->create(['site_id' => $site->id, 'slug' => 'home', 'status' => Page::STATUS_DRAFT]);
    PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $slotType->id, 'sort_order' => 0]);

    return [$page, $slotType];
  }

  /**
   * @return array{0: Block, 1: BlockType}
   */
  private function seedColumns(Page $page, SlotType $slotType): array
  {
    $columnsType = $this->seedBlockType('Columns', 'columns', true, 0);
    $itemType = $this->seedBlockType('Column Item', 'column_item', false, 1);

    $columns = Block::query()->create([
      'page_id' => $page->id, 'type' => 'columns', 'block_type_id' => $columnsType->id,
      'source_type' => 'static', 'slot' => $slotType->slug, 'slot_type_id' => $slotType->id,
      'sort_order' => 0, 'status' => 'published', 'title' => 'Columns',
    ]);

    return [$columns, $itemType];
  }

  private function seedBlockType(string $name, string $slug, bool $isContainer, int $sortOrder): BlockType
  {
    return BlockType::query()->create([
      'name' => $name, 'slug' => $slug, 'category' => 'content',
      'is_container' => $isContainer, 'source_type' => 'static',
      'is_system' => false, 'sort_order' => $sortOrder, 'status' => 'published',
    ]);
  }

  private function seedColumnItem(Page $page, SlotType $slotType, Block $columns, BlockType $itemType, string $title, ?string $content): Block
  {
    return Block::query()->create([
      'page_id' => $page->id, 'parent_id' => $columns->id, 'type' => 'column_item',
      'block_type_id' => $itemType->id, 'source_type' => 'static',
      'slot' => $slotType->slug, 'slot_type_id' => $slotType->id,
      'sort_order' => $columns->children()->count(),
      'status' => 'published', 'title' => $title, 'content' => $content,
    ]);
  }
}
