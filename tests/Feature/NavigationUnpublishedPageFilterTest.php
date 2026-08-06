<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Every public menu renderer follows the same rule for page-linked items:
 * the link only renders while the page is published. The navbar always
 * enforced this, but the footer/legal menu (navigation-auto) and the
 * sidebar menu only checked the item's own visibility -- so archiving a
 * page dropped its link from the navbar while the footer kept a dead
 * link to the now-404 page.
 */
class NavigationUnpublishedPageFilterTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function the_footer_menu_drops_links_to_unpublished_pages(): void
  {
    $html = $this->renderMenuBlock('navigation-auto');

    $this->assertStringContainsString('>About</a>', $html);
    $this->assertStringNotContainsString('Archive', $html);
  }

  #[Test]
  public function the_sidebar_menu_drops_links_to_unpublished_pages(): void
  {
    $html = $this->renderMenuBlock('sidebar-navigation');

    $this->assertStringContainsString('About', $html);
    $this->assertStringNotContainsString('Archive', $html);
  }

  private function renderMenuBlock(string $typeSlug): string
  {
    $site = Site::query()->firstOrCreate(['handle' => 'test'], ['name' => 'Test', 'is_primary' => true]);
    $locale = Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);

    $published = $this->seedPage($site->id, $locale->id, 'About', 'about', Page::STATUS_PUBLISHED);
    $archived = $this->seedPage($site->id, $locale->id, 'Archive', 'archive', Page::STATUS_ARCHIVED);

    // Both items stay visible: the archived page's link must disappear
    // because of the page status alone, exactly like the navbar.
    foreach ([['About', $published], ['Archive', $archived]] as $position => [$title, $page]) {
      NavigationItem::query()->create([
        'site_id' => $site->id,
        'menu_key' => NavigationItem::MENU_FOOTER,
        'title' => $title,
        'link_type' => NavigationItem::LINK_PAGE,
        'page_id' => $page->id,
        'position' => $position,
        'visibility' => NavigationItem::VISIBILITY_VISIBLE,
      ]);
    }

    $blockType = BlockType::query()->firstOrCreate(['slug' => $typeSlug], ['name' => $typeSlug, 'is_active' => true]);

    $block = Block::query()->create([
      'page_id' => $published->id,
      'type' => $typeSlug,
      'block_type_id' => $blockType->id,
      'source_type' => 'static',
      'slot' => 'footer',
      'sort_order' => 0,
      'status' => 'published',
      'settings' => json_encode(['menu_key' => NavigationItem::MENU_FOOTER]),
    ]);

    return view('webblocks-cms::pages.partials.blocks.'.$typeSlug, [
      'block' => $block->fresh(['blockType', 'page']),
    ])->render();
  }

  private function seedPage(int $siteId, int $localeId, string $name, string $slug, string $status): Page
  {
    $page = Page::query()->create([
      'site_id' => $siteId,
      'page_type' => Page::TYPE_DEFAULT,
      'status' => $status,
      'published_at' => now(),
    ]);

    PageTranslation::query()->create([
      'page_id' => $page->id,
      'site_id' => $siteId,
      'locale_id' => $localeId,
      'name' => $name,
      'slug' => $slug,
      'path' => '/'.$slug,
    ]);

    return $page;
  }
}
