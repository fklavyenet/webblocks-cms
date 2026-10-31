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
  public function it_includes_automatic_anchors_but_only_h2_and_h3(): void
  {
    [$page, $mainSlot] = $this->seedPageWithTwoSlots();

    $this->header($page, $mainSlot, 'ok', 'Kept', 'h2', sortOrder: 0);
    $this->header($page, $mainSlot, null, 'No anchor', 'h2', sortOrder: 1);
    $this->header($page, $mainSlot, 'too-deep', 'Wrong level', 'h4', sortOrder: 2);
    $toc = $this->toc($page, $mainSlot, sortOrder: 3);

    $this->assertSame(['Kept', 'No anchor'], $toc->publicTocHeadingBlocks('en')->pluck('title')->all());
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

  #[Test]
  public function excluded_blank_and_unpublished_branches_are_not_listed_even_when_preloaded(): void
  {
    [$page, $slot] = $this->seedPageWithTwoSlots();
    $kept = $this->header($page, $slot, null, 'Kept', 'h2', 0);
    $excluded = $this->header($page, $slot, 'excluded', 'Excluded', 'h3', 1);
    $excluded->update(['settings' => ['anchor' => 'excluded', 'include_in_toc' => false]]);
    $this->header($page, $slot, 'blank', '   ', 'h2', 2);
    $parent = Block::create(['page_id' => $page->id, 'slot_type_id' => $slot->id, 'type' => 'section', 'status' => 'draft']);
    $this->header($page, $slot, 'hidden-child', 'Hidden child', 'h2', 0, $parent->id);
    $draft = $this->header($page, $slot, 'draft', 'Draft', 'h2', 3);
    $draft->update(['status' => 'draft']);
    $toc = $this->toc($page, $slot, 4);
    $toc->setRelation('renderPage', $page->load('blocks'));
    $this->assertSame([$kept->id], $toc->publicTocHeadingBlocks('en')->pluck('id')->all());
    $toc->setAttribute('render_preview', true);
    $this->assertContains($draft->id, $toc->publicTocHeadingBlocks('en')->pluck('id')->all());
  }

  #[Test]
  public function deeply_nested_mixed_levels_keep_the_slot_reading_order(): void
  {
    [$page, $slot] = $this->seedPageWithTwoSlots();
    $parent = null;
    for ($depth = 0; $depth < 8; $depth++) {
      $parent = Block::create(['page_id' => $page->id, 'slot_type_id' => $slot->id, 'parent_id' => $parent?->id, 'type' => 'container', 'status' => 'published']);
    }
    $this->header($page, $slot, 'title', 'Title', 'h1', 0, $parent->id);
    $first = $this->header($page, $slot, 'deep', 'Deep section', 'h2', 1, $parent->id);
    $second = $this->header($page, $slot, 'detail', 'Detail', 'h3', 2, $parent->id);
    $this->header($page, $slot, 'minor', 'Minor', 'h4', 3, $parent->id);
    $toc = $this->toc($page, $slot, 1);
    $this->assertSame([$first->id, $second->id], $toc->publicTocHeadingBlocks('en')->pluck('id')->all());
  }

  #[Test]
  public function automatic_targets_survive_copy_edits_and_custom_collisions_remain_unique(): void
  {
    [$page, $slot] = $this->seedPageWithTwoSlots();
    $automatic = $this->header($page, $slot, null, 'Original', 'h2', 0);
    $target = $automatic->headerAnchor();
    $automatic->update(['title' => 'Renamed']);
    $this->assertSame($target, $automatic->fresh()->headerAnchor());
    $first = $this->header($page, $slot, 'repeated', 'First', 'h2', 1);
    $second = $this->header($page, $slot, 'repeated', 'Second', 'h3', 2);
    $reserved = $this->header($page, $slot, 'wb-heading-'.$second->id, 'Reserved', 'h2', 3);
    $this->assertSame('repeated', $first->headerAnchor());
    $this->assertSame('wb-heading-'.$second->id.'-2', $second->headerAnchor());
    $this->assertSame('wb-heading-'.$second->id, $reserved->headerAnchor());
    $toc = $this->toc($page, $slot, 4);
    $html = view('webblocks-cms::pages.partials.blocks.toc', ['block' => $toc])->render();
    foreach ([$automatic, $first, $second, $reserved] as $header) {
      $target = $header->headerAnchor();
      $this->assertStringContainsString('href="#'.$target.'"', $html);
      $this->assertStringContainsString('id="'.$target.'"', view('webblocks-cms::pages.partials.blocks.header', ['block' => $header])->render());
    }
  }

  #[Test]
  public function translated_labels_share_the_same_anchor_and_inclusion_choice(): void
  {
    [$page, $slot] = $this->seedPageWithTwoSlots();
    $tr = Locale::create(['code' => 'tr', 'name' => 'Turkish', 'is_enabled' => true]);
    $page->site->locales()->syncWithoutDetaching([$tr->id => ['is_enabled' => true], Locale::where('code', 'en')->value('id') => ['is_enabled' => true]]);
    $header = $this->header($page, $slot, null, 'English heading', 'h2', 0);
    $header->textTranslations()->create(['locale_id' => $tr->id, 'title' => 'Türkçe başlık']);
    $toc = $this->toc($page, $slot, 1);
    $translated = $toc->publicTocHeadingBlocks('tr')->first();
    $this->assertSame('Türkçe başlık', $translated->tocEligibleHeadingText());
    $this->assertSame($header->headerAnchor(), $translated->headerAnchor());
    $header->update(['settings' => [...$header->decodedSettings(), 'include_in_toc' => false]]);
    $this->assertTrue($toc->publicTocHeadingBlocks('tr')->isEmpty());
    $this->assertTrue($toc->publicTocHeadingBlocks('en')->isEmpty());
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
