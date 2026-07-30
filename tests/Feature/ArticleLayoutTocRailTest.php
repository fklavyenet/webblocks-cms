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
 * Article Layout pulls a top-level toc block out of main's normal flow into a
 * narrow sticky rail beside the rest of the content, by reusing the exact
 * wb-settings-shell.wb-docs-layout grid already shipped for the Settings
 * Shell pattern — no new CSS, and it only activates when both conditions
 * hold: the page is on this layout, and main actually has a toc block as one
 * of its direct children. Anything else renders exactly like Default Layout.
 */
class ArticleLayoutTocRailTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function a_top_level_toc_is_pulled_into_the_rail_on_article_layout(): void
  {
    $html = $this->renderMain(layout: 'article', suffix: 'a', withToc: true);

    $this->assertStringContainsString('wb-settings-shell wb-docs-layout', $html);
    $this->assertStringContainsString('wb-settings-body', $html);
    $this->assertStringContainsString('wb-settings-nav wb-docs-rail', $html);
    $this->assertStringContainsString('wb-section-nav', $html);

    // The rail wrapper carries the toc's own root directly -- toc is not in
    // Block::ownsPublicRoot()'s list, so it still gets the generic
    // wb-public-block marker div, same as it would outside the rail.
    $railStart = strpos($html, 'wb-settings-nav wb-docs-rail');
    $navStart = strpos($html, 'wb-section-nav');
    $this->assertNotFalse($railStart);
    $this->assertNotFalse($navStart);
    $this->assertGreaterThan($railStart, $navStart, 'The section-nav must render inside the rail wrapper.');

    // The rest of main renders inside wb-settings-body, not the rail.
    $bodyStart = strpos($html, 'wb-settings-body');
    $this->assertLessThan($railStart, $bodyStart, 'wb-settings-body must come before the rail in source order.');
    $this->assertStringContainsString('Article body', substr($html, $bodyStart, $railStart - $bodyStart));
  }

  #[Test]
  public function a_page_not_on_article_layout_never_splits_even_with_a_toc(): void
  {
    $html = $this->renderMain(layout: 'default', suffix: 'b', withToc: true);

    $this->assertStringNotContainsString('wb-settings-shell', $html);
    $this->assertStringNotContainsString('wb-docs-rail', $html);
    // toc still renders, just inline in the normal single-column flow.
    $this->assertStringContainsString('wb-section-nav', $html);
  }

  #[Test]
  public function article_layout_without_a_toc_block_renders_a_single_column(): void
  {
    $html = $this->renderMain(layout: 'article', suffix: 'c', withToc: false);

    $this->assertStringNotContainsString('wb-settings-shell', $html);
    $this->assertStringNotContainsString('wb-docs-rail', $html);
    $this->assertStringContainsString('wb-stack wb-gap-6', $html);
  }

  #[Test]
  public function a_nested_toc_is_not_promoted(): void
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test-d', 'is_primary' => true]);
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $mainSlot = SlotType::query()->create(['name' => 'Main', 'slug' => 'main', 'status' => 'published', 'sort_order' => 0]);
    $page = Page::query()->create(['site_id' => $site->id, 'slug' => 'article-d', 'status' => Page::STATUS_DRAFT, 'settings' => ['public_shell' => 'article']]);
    PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $mainSlot->id, 'sort_order' => 0]);

    // toc nested one level inside a container -- not a direct child of main.
    $container = Block::create(['page_id' => $page->id, 'parent_id' => null, 'type' => 'container', 'slot_type_id' => $mainSlot->id, 'sort_order' => 0, 'status' => 'published']);
    Block::create(['page_id' => $page->id, 'parent_id' => $container->id, 'type' => 'toc', 'slot_type_id' => $mainSlot->id, 'sort_order' => 0, 'status' => 'published']);

    $html = $this->renderSlot($page, $mainSlot, [$container]);

    $this->assertStringNotContainsString('wb-settings-shell', $html);
    $this->assertStringNotContainsString('wb-docs-rail', $html);
  }

  private function renderMain(string $layout, string $suffix, bool $withToc): string
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test-'.$suffix, 'is_primary' => true]);
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $mainSlot = SlotType::query()->create(['name' => 'Main', 'slug' => 'main', 'status' => 'published', 'sort_order' => 0]);
    $page = Page::query()->create([
      'site_id' => $site->id,
      'slug' => 'article-'.$suffix,
      'status' => Page::STATUS_DRAFT,
      'settings' => ['public_shell' => $layout],
    ]);
    PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $mainSlot->id, 'sort_order' => 0]);

    $blocks = [];
    // h2 with an anchor so it doubles as a real TOC-eligible heading -- an
    // empty toc (no eligible headings) renders nothing at all, which would
    // make the rail assertions below vacuous.
    $blocks[] = Block::create(['page_id' => $page->id, 'parent_id' => null, 'type' => 'header', 'slot_type_id' => $mainSlot->id, 'sort_order' => 0, 'title' => 'Article body', 'variant' => 'h2', 'settings' => json_encode(['anchor' => 'article-body']), 'status' => 'published']);

    if ($withToc) {
      $blocks[] = Block::create(['page_id' => $page->id, 'parent_id' => null, 'type' => 'toc', 'slot_type_id' => $mainSlot->id, 'sort_order' => 1, 'status' => 'published']);
    }

    return $this->renderSlot($page, $mainSlot, $blocks);
  }

  private function renderSlot(Page $page, SlotType $mainSlot, array $blocks): string
  {
    $slot = [
      'slug' => 'main',
      'wrapper' => [],
      'blocks' => collect($blocks),
    ];

    return view('webblocks-cms::pages.partials.slots.main', ['slot' => $slot, 'page' => $page, 'renderShell' => false])->render();
  }
}
