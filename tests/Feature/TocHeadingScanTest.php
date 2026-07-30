<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;
use WebBlocks\Cms\Tests\TestCase;

/**
 * A table of contents describes the slot it lives in, not the page. It used
 * to scan every header on the page regardless of slot, and sort headings by
 * a (sort_order, id) pair that is only meaningful within one parent — two
 * headings under different sections both start counting sort_order from 0,
 * so a flat sort does not reconstruct reading order once content has more
 * than one section.
 */
class TocHeadingScanTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function toc_is_a_system_block_type(): void
  {
    $definition = collect(app(CoreBlockTypeCatalogSyncer::class)->definitions())
      ->firstWhere('slug', 'toc');

    $this->assertNotNull($definition, 'The core catalog must still define a toc block type.');
    $this->assertTrue($definition['is_system'], 'toc must be a system block type, matching comments/rating/breadcrumb/navigation-auto.');
  }

  #[Test]
  public function it_only_collects_headings_from_its_own_slot(): void
  {
    [$page, $mainSlot, $sidebarSlot] = $this->seedPageWithTwoSlots();

    $mainHeading = $this->header($page, $mainSlot, 'intro', 'Introduction', 'h2', sortOrder: 0);
    $this->header($page, $sidebarSlot, 'unrelated', 'Unrelated sidebar heading', 'h2', sortOrder: 0);
    $toc = $this->toc($page, $mainSlot, sortOrder: 1);

    $headings = $toc->publicTocHeadingBlocks('en');

    $this->assertSame([$mainHeading->id], $headings->pluck('id')->all());
  }

  #[Test]
  public function it_walks_the_slot_in_document_order_across_sections(): void
  {
    [$page, $mainSlot] = $this->seedPageWithTwoSlots();

    // Two sibling "section" containers, each numbering sort_order from 0 for
    // its own children — exactly the layout a real article uses (section >
    // container > header). A flat sort by (sort_order, id) would interleave
    // these wrong; a document-order walk must not.
    $sectionA = Block::create(['page_id' => $page->id, 'parent_id' => null, 'type' => 'section', 'slot_type_id' => $mainSlot->id, 'sort_order' => 0, 'status' => 'published']);
    $sectionB = Block::create(['page_id' => $page->id, 'parent_id' => null, 'type' => 'section', 'slot_type_id' => $mainSlot->id, 'sort_order' => 1, 'status' => 'published']);

    $firstInA = $this->header($page, $mainSlot, 'first', 'First', 'h2', sortOrder: 0, parentId: $sectionA->id);
    $secondInA = $this->header($page, $mainSlot, 'second', 'Second', 'h3', sortOrder: 1, parentId: $sectionA->id);
    $firstInB = $this->header($page, $mainSlot, 'third', 'Third', 'h2', sortOrder: 0, parentId: $sectionB->id);

    $toc = $this->toc($page, $mainSlot, sortOrder: 2);

    $this->assertSame(
      [$firstInA->id, $secondInA->id, $firstInB->id],
      $toc->publicTocHeadingBlocks('en')->pluck('id')->all(),
      'Headings must come back in reading order: section A top-to-bottom, then section B — not grouped by their independently-numbered sort_order.'
    );
  }

  #[Test]
  public function it_still_requires_h2_or_h3_and_a_valid_anchor(): void
  {
    [$page, $mainSlot] = $this->seedPageWithTwoSlots();

    $this->header($page, $mainSlot, 'ok', 'Kept', 'h2', sortOrder: 0);
    $this->header($page, $mainSlot, null, 'No anchor', 'h2', sortOrder: 1);
    $this->header($page, $mainSlot, 'too-deep', 'Wrong level', 'h4', sortOrder: 2);
    $toc = $this->toc($page, $mainSlot, sortOrder: 3);

    $this->assertSame(['Kept'], $toc->publicTocHeadingBlocks('en')->pluck('title')->all());
  }

  #[Test]
  public function a_toc_block_never_lists_itself(): void
  {
    [$page, $mainSlot] = $this->seedPageWithTwoSlots();

    $heading = $this->header($page, $mainSlot, 'only', 'Only heading', 'h2', sortOrder: 0);
    $toc = $this->toc($page, $mainSlot, sortOrder: 1);

    $ids = $toc->publicTocHeadingBlocks('en')->pluck('id')->all();

    $this->assertSame([$heading->id], $ids);
    $this->assertNotContains($toc->id, $ids);
  }

  private function header(Page $page, SlotType $slot, ?string $anchor, string $title, string $variant, int $sortOrder, ?int $parentId = null): Block
  {
    return Block::create([
      'page_id' => $page->id,
      'parent_id' => $parentId,
      'type' => 'header',
      'slot_type_id' => $slot->id,
      'sort_order' => $sortOrder,
      'title' => $title,
      'variant' => $variant,
      'settings' => $anchor !== null ? json_encode(['anchor' => $anchor]) : null,
      'status' => 'published',
    ]);
  }

  private function toc(Page $page, SlotType $slot, int $sortOrder): Block
  {
    return Block::create([
      'page_id' => $page->id,
      'parent_id' => null,
      'type' => 'toc',
      'slot_type_id' => $slot->id,
      'sort_order' => $sortOrder,
      'status' => 'published',
    ]);
  }

  /**
   * @return array{0: Page, 1: SlotType, 2: SlotType}
   */
  private function seedPageWithTwoSlots(): array
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $mainSlot = SlotType::query()->create(['name' => 'Main', 'slug' => 'main', 'status' => 'published', 'sort_order' => 0]);
    $sidebarSlot = SlotType::query()->create(['name' => 'Sidebar', 'slug' => 'sidebar', 'status' => 'published', 'sort_order' => 1]);
    $page = Page::query()->create(['site_id' => $site->id, 'slug' => 'article', 'status' => Page::STATUS_DRAFT]);
    PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $mainSlot->id, 'sort_order' => 0]);
    PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $sidebarSlot->id, 'sort_order' => 1]);

    return [$page, $mainSlot, $sidebarSlot];
  }
}
