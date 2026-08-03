<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockPayloadWriter;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Saving one Shared Slot block reindexed every page using the slot, once per
 * written row.
 *
 * One editor save is never one row: the block, then up to four translation
 * families, each with its own save hook asking for the same reindex. On a
 * page-owned block that rebuilt one page several times over; on a Shared Slot
 * — the site header, used by every published page — it rebuilt all of them
 * several times over, and "Save Block" took seconds on a site with almost no
 * content in it.
 */
class SharedSlotSearchReindexTest extends TestCase
{
  /** @var list<string> */
  private array $indexWrites = [];

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function a_coalesced_save_rebuilds_each_page_using_the_slot_once(): void
  {
    [$sourcePage, $block] = $this->seedSharedSlotUsedByPages(3);

    $writes = $this->countIndexWrites(function () use ($sourcePage, $block): void {
      PublicSearchIndexer::coalescing(function () use ($sourcePage, $block): void {
        $this->saveBlock($block, $sourcePage, 'Coalesced header');
      });
    });

    $this->assertSame(3, $writes, 'Three pages use the slot, so three index rows are rewritten — one each.');
  }

  #[Test]
  public function the_same_save_uncoalesced_repeats_the_whole_sweep(): void
  {
    [$sourcePage, $block] = $this->seedSharedSlotUsedByPages(3);

    $writes = $this->countIndexWrites(function () use ($sourcePage, $block): void {
      $this->saveBlock($block, $sourcePage, 'Live header');
    });

    // Guards the premise rather than the fix: if a save ever costs one sweep on
    // its own, coalescing has stopped being the thing that makes it one.
    $this->assertGreaterThan(
      3,
      $writes,
      'Without coalescing the save hooks each run their own full sweep.',
    );
  }

  #[Test]
  public function the_coalesced_index_matches_what_the_live_path_would_have_written(): void
  {
    [$sourcePage, $block] = $this->seedSharedSlotUsedByPages(2);

    PublicSearchIndexer::coalescing(function () use ($sourcePage, $block): void {
      $this->saveBlock($block, $sourcePage, 'Deferred wording');
    });

    $coalesced = $this->indexedContent();

    $this->saveBlock($block, $sourcePage, 'Deferred wording');

    $this->assertSame(
      $coalesced,
      $this->indexedContent(),
      'Collapsing the repeats must not change the index they were building.',
    );
    $this->assertStringContainsString('Deferred wording', implode(' ', $coalesced));
  }

  #[Test]
  public function nested_scopes_only_flush_at_the_outermost_exit(): void
  {
    [$sourcePage, $block] = $this->seedSharedSlotUsedByPages(2);

    $writesInside = 0;

    $writes = $this->countIndexWrites(function () use ($sourcePage, $block, &$writesInside): void {
      PublicSearchIndexer::coalescing(function () use ($sourcePage, $block, &$writesInside): void {
        PublicSearchIndexer::coalescing(function () use ($sourcePage, $block): void {
          $this->saveBlock($block, $sourcePage, 'Nested header');
        });

        $writesInside = count($this->indexWrites);
      });
    });

    $this->assertSame(0, $writesInside, 'The inner scope closed, but the outer write is still in progress.');
    $this->assertSame(2, $writes);
    $this->assertFalse(PublicSearchIndexer::isCoalescing());
  }

  #[Test]
  public function a_failed_write_leaves_nothing_queued_and_reopens_the_live_path(): void
  {
    [$sourcePage, $block] = $this->seedSharedSlotUsedByPages(2);

    $writes = $this->countIndexWrites(function () use ($sourcePage, $block): void {
      try {
        PublicSearchIndexer::coalescing(function () use ($sourcePage, $block): void {
          $this->saveBlock($block, $sourcePage, 'Rolled back');

          throw new RuntimeException('Block save failed.');
        });
        $this->fail('Expected the callback exception to propagate.');
      } catch (RuntimeException $exception) {
        $this->assertSame('Block save failed.', $exception->getMessage());
      }
    });

    $this->assertSame(0, $writes, 'A write that threw has nothing worth reindexing.');
    $this->assertFalse(PublicSearchIndexer::isCoalescing());
  }

  #[Test]
  public function a_bulk_import_deferral_still_wins_over_coalescing(): void
  {
    [$sourcePage, $block] = $this->seedSharedSlotUsedByPages(2);

    $writes = $this->countIndexWrites(function () use ($sourcePage, $block): void {
      PublicSearchIndexer::coalescing(function () use ($sourcePage, $block): void {
        PublicSearchIndexer::deferring(function () use ($sourcePage, $block): void {
          $this->saveBlock($block, $sourcePage, 'Imported header');
        });
      });
    });

    // A deferring bulk writer rebuilds the whole index itself when it is done.
    // Queueing its rows here too would repeat the cost the deferral just saved.
    $this->assertSame(0, $writes);
  }

  private function saveBlock(Block $block, Page $sourcePage, string $title): void
  {
    app(BlockPayloadWriter::class)->save($block, $sourcePage, [
      'page_id' => $sourcePage->id,
      'block_type_id' => $block->block_type_id,
      'slot_type_id' => $block->slot_type_id,
      'sort_order' => $block->sort_order,
      'status' => 'published',
      'title' => $title,
    ], null);
  }

  /**
   * @return list<string>
   */
  private function indexedContent(): array
  {
    return DB::table('wbcms_public_search_index')
      ->orderBy('page_id')
      ->pluck('content')
      ->map(fn ($content): string => (string) $content)
      ->all();
  }

  private function countIndexWrites(callable $work): int
  {
    $this->indexWrites = [];

    DB::listen(function ($query): void {
      $sql = strtolower($query->sql);

      if (! str_contains($sql, 'wbcms_public_search_index')) {
        return;
      }

      if (str_starts_with($sql, 'insert into') || str_starts_with($sql, 'update')) {
        $this->indexWrites[] = $query->sql;
      }
    });

    $work();

    return count($this->indexWrites);
  }

  /**
   * A Shared Slot holding one block, plus $pageCount published pages that all
   * render it through a slot bound to the Shared Slot.
   *
   * @return array{0: Page, 1: Block}
   */
  private function seedSharedSlotUsedByPages(int $pageCount): array
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    $locale = Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
    $slotType = SlotType::query()->create(['name' => 'Header', 'slug' => 'header', 'status' => 'published', 'sort_order' => 0]);
    $blockType = BlockType::query()->create([
      'name' => 'Rich text',
      'slug' => 'rich-text',
      'category' => 'content',
      'source_type' => 'static',
      'is_system' => false,
      'is_container' => false,
      'sort_order' => 0,
      'status' => 'published',
    ]);

    $sharedSlot = SharedSlot::query()->create([
      'site_id' => $site->id,
      'name' => 'Site Header',
      'handle' => 'site-header',
      'slot_name' => $slotType->slug,
      'is_active' => true,
    ]);

    $sourcePage = app(SharedSlotSourcePageManager::class)->ensureFor($sharedSlot);

    $block = Block::query()->create([
      'page_id' => $sourcePage->id,
      'type' => $blockType->slug,
      'block_type_id' => $blockType->id,
      'source_type' => 'static',
      'slot' => $slotType->slug,
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'status' => 'published',
      'title' => 'Original header',
    ]);

    for ($index = 0; $index < $pageCount; $index++) {
      $page = Page::query()->create([
        'site_id' => $site->id,
        'title' => 'Page '.$index,
        'slug' => 'page-'.$index,
        'status' => Page::STATUS_PUBLISHED,
      ]);

      PageSlot::query()->create([
        'page_id' => $page->id,
        'slot_type_id' => $slotType->id,
        'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
        'shared_slot_id' => $sharedSlot->id,
        'sort_order' => 0,
      ]);

      $this->assertNotNull(
        $page->translationForLocale($locale),
        'The seeded page needs a translation, or nothing about it is indexable.',
      );
    }

    app(SharedSlotSourcePageManager::class)->rebuildAssignments($sharedSlot);

    return [$sourcePage, $block->fresh()];
  }
}
