<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The public navbar mobile menu follows the shipped WebBlocks UI drawer
 * contract: the navigation block renders a wb-navbar-toggle wired through
 * the generic collapse runtime plus desktop wb-navbar-links, and pushes a
 * wb-navbar-drawer that the navbar container renders after its own nav.
 */
class NavbarDrawerRenderingTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function the_navbar_renders_the_shipped_drawer_contract(): void
  {
    $html = $this->renderNavbar();

    // Toggle wired through the generic collapse runtime.
    $this->assertStringContainsString('wb-navbar-toggle', $html);
    $this->assertMatchesRegularExpression('/data-wb-collapse="wb-navbar-drawer-\d+"/', $html);

    // Desktop links stay on the shipped anatomy.
    $this->assertStringContainsString('wb-navbar-links', $html);
    $this->assertStringContainsString('wb-navbar-link', $html);

    // The drawer renders after the nav element, not inside it.
    $this->assertStringContainsString('<div class="wb-navbar-drawer"', $html);
    $navClose = strpos($html, '</nav>');
    $drawerStart = strpos($html, '<div class="wb-navbar-drawer"');
    $this->assertNotFalse($navClose);
    $this->assertNotFalse($drawerStart);
    $this->assertGreaterThan($navClose, $drawerStart, 'Drawer must render after </nav>.');

    // Menu content lands in both the desktop list and the drawer.
    $this->assertSame(2, substr_count($html, '>Docs</a>'));

    // The retired dropdown-based mobile layer is gone.
    $this->assertStringNotContainsString('wb-cms-navbar-mobile', $html);
    $this->assertStringNotContainsString('wb-cms-navbar-navigation', $html);
  }

  #[Test]
  public function group_items_render_as_label_rows_inside_the_drawer(): void
  {
    $html = $this->renderNavbar(withGroup: true);

    $drawer = substr($html, strpos($html, '<div class="wb-navbar-drawer"'));
    $this->assertStringContainsString('Resources', $drawer);
    $this->assertStringContainsString('>Guides</a>', $drawer);
  }

  private function renderNavbar(bool $withGroup = false): string
  {
    $this->seedBlockTypes();
    [$page, $slotType] = $this->seedPage();

    NavigationItem::query()->create([
      'site_id' => $page->site_id,
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Docs',
      'link_type' => NavigationItem::LINK_CUSTOM_URL,
      'url' => '/docs',
      'position' => 0,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    if ($withGroup) {
      $group = NavigationItem::query()->create([
        'site_id' => $page->site_id,
        'menu_key' => NavigationItem::MENU_PRIMARY,
        'title' => 'Resources',
        'link_type' => NavigationItem::LINK_GROUP,
        'position' => 1,
        'visibility' => NavigationItem::VISIBILITY_VISIBLE,
      ]);
      NavigationItem::query()->create([
        'site_id' => $page->site_id,
        'menu_key' => NavigationItem::MENU_PRIMARY,
        'parent_id' => $group->id,
        'title' => 'Guides',
        'link_type' => NavigationItem::LINK_CUSTOM_URL,
        'url' => '/guides',
        'position' => 0,
        'visibility' => NavigationItem::VISIBILITY_VISIBLE,
      ]);
    }

    $navbarType = BlockType::query()->where('slug', 'sticky-navbar')->firstOrFail();
    $navigationType = BlockType::query()->where('slug', 'navbar-navigation')->firstOrFail();

    $navbar = Block::query()->create([
      'page_id' => $page->id, 'type' => 'sticky-navbar', 'block_type_id' => $navbarType->id,
      'source_type' => 'static', 'slot' => $slotType->slug, 'slot_type_id' => $slotType->id,
      'sort_order' => 0, 'status' => 'published',
    ]);

    Block::query()->create([
      'page_id' => $page->id, 'type' => 'navbar-navigation', 'block_type_id' => $navigationType->id,
      'parent_id' => $navbar->id, 'source_type' => 'static', 'slot' => $slotType->slug,
      'slot_type_id' => $slotType->id, 'sort_order' => 0, 'status' => 'published',
      'settings' => json_encode(['menu_key' => NavigationItem::MENU_PRIMARY]),
    ]);

    return view('webblocks-cms::pages.partials.blocks.sticky-navbar', [
      'block' => $navbar->fresh(['blockType', 'children.blockType']),
    ])->render();
  }

  private function seedBlockTypes(): void
  {
    foreach ([
      ['slug' => 'sticky-navbar', 'name' => 'Navbar'],
      ['slug' => 'navbar-navigation', 'name' => 'Navbar Navigation'],
    ] as $definition) {
      BlockType::query()->firstOrCreate(['slug' => $definition['slug']], $definition + ['is_active' => true]);
    }
  }

  private function seedPage(): array
  {
    $site = Site::query()->firstOrCreate(['handle' => 'test'], ['name' => 'Test', 'is_primary' => true]);
    Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $slotType = SlotType::query()->firstOrCreate(['slug' => 'header'], ['name' => 'Header', 'status' => 'published', 'sort_order' => 0]);
    $page = Page::query()->firstOrCreate(['site_id' => $site->id, 'slug' => 'home'], ['status' => Page::STATUS_PUBLISHED]);
    PageSlot::query()->firstOrCreate(['page_id' => $page->id, 'slot_type_id' => $slotType->id], ['sort_order' => 0]);

    return [$page, $slotType];
  }
}
