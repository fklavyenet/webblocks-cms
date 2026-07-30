<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Tests\TestCase;

/**
 * TOC renders wb-section-nav, not wb-link-list. It is a self-contained
 * WebBlocks UI primitive (its own border/background/padding, no dependency
 * on the Settings Shell docs pattern it is normally seen inside), and the
 * exact webblocks-ui.js the public layout already loads ships a WBSectionNav
 * scrollspy that auto-initializes on any `.wb-section-nav` it finds -- for
 * free, with no JavaScript owned by this package.
 */
class TocSectionNavMarkupTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function it_renders_the_section_nav_primitive_not_link_list(): void
  {
    $html = $this->renderToc(title: 'On this page');

    foreach (['wb-section-nav"', 'wb-section-nav-title', 'wb-section-nav-list', 'wb-section-nav-item', 'wb-section-nav-link'] as $expected) {
      $this->assertStringContainsString($expected, $html);
    }

    foreach (['wb-link-list', 'wb-stack', 'wb-gap-2'] as $retired) {
      $this->assertStringNotContainsString($retired, $html, "The retired {$retired} class must not appear in TOC output.");
    }
  }

  #[Test]
  public function it_leaves_scroll_highlighting_to_the_shipped_webblocks_ui_runtime(): void
  {
    $html = $this->renderToc(title: 'On this page');

    // No hardcoded English chrome (this text used to leak onto German pages
    // regardless of site locale), and no renderer-owned JavaScript: the
    // wb-section-nav class alone is what the shipped runtime keys off.
    foreach (['Jump to section', 'Jump to subsection', 'Section detail', '<script'] as $absent) {
      $this->assertStringNotContainsString($absent, $html);
    }
  }

  #[Test]
  public function the_title_becomes_the_accessible_label(): void
  {
    $withTitle = $this->renderToc(title: 'Article contents', suffix: 'a');
    $this->assertStringContainsString('aria-label="Article contents"', $withTitle);
    $this->assertStringContainsString('<div class="wb-section-nav-title">Article contents</div>', $withTitle);
  }

  #[Test]
  public function a_blank_title_omits_the_label_and_the_heading_div(): void
  {
    $withoutTitle = $this->renderToc(title: null, suffix: 'b');
    $this->assertStringNotContainsString('aria-label', $withoutTitle);
    $this->assertStringNotContainsString('wb-section-nav-title', $withoutTitle);
  }

  #[Test]
  public function links_target_the_heading_anchors_in_document_order(): void
  {
    $html = $this->renderToc(title: null);

    $this->assertStringContainsString('href="#first"', $html);
    $this->assertStringContainsString('href="#second"', $html);
    $this->assertLessThan(strpos($html, 'href="#second"'), strpos($html, 'href="#first"'));
  }

  #[Test]
  public function it_renders_nothing_when_no_heading_is_eligible(): void
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $mainSlot = SlotType::query()->create(['name' => 'Main', 'slug' => 'main', 'status' => 'published', 'sort_order' => 0]);
    $page = Page::query()->create(['site_id' => $site->id, 'slug' => 'empty', 'status' => Page::STATUS_DRAFT]);
    PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $mainSlot->id, 'sort_order' => 0]);

    $toc = Block::create(['page_id' => $page->id, 'parent_id' => null, 'type' => 'toc', 'slot_type_id' => $mainSlot->id, 'sort_order' => 0, 'status' => 'published']);

    $html = view('webblocks-cms::pages.partials.blocks.toc', ['block' => $toc])->render();

    $this->assertSame('', trim($html));
  }

  private function renderToc(?string $title, string $suffix = 'x'): string
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test-'.$suffix, 'is_primary' => true]);
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $mainSlot = SlotType::query()->create(['name' => 'Main', 'slug' => 'main', 'status' => 'published', 'sort_order' => 0]);
    $page = Page::query()->create(['site_id' => $site->id, 'slug' => 'article-'.$suffix, 'status' => Page::STATUS_DRAFT]);
    PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $mainSlot->id, 'sort_order' => 0]);

    Block::create(['page_id' => $page->id, 'parent_id' => null, 'type' => 'header', 'slot_type_id' => $mainSlot->id, 'sort_order' => 0, 'title' => 'First', 'variant' => 'h2', 'settings' => json_encode(['anchor' => 'first']), 'status' => 'published']);
    Block::create(['page_id' => $page->id, 'parent_id' => null, 'type' => 'header', 'slot_type_id' => $mainSlot->id, 'sort_order' => 1, 'title' => 'Second', 'variant' => 'h3', 'settings' => json_encode(['anchor' => 'second']), 'status' => 'published']);

    $toc = Block::create([
      'page_id' => $page->id,
      'parent_id' => null,
      'type' => 'toc',
      'slot_type_id' => $mainSlot->id,
      'sort_order' => 2,
      'title' => $title,
      'status' => 'published',
    ]);

    return view('webblocks-cms::pages.partials.blocks.toc', ['block' => $toc])->render();
  }
}
