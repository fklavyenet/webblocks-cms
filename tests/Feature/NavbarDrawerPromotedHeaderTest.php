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
use WebBlocks\Cms\Support\Pages\PublicPagePresenter;
use WebBlocks\Cms\Tests\TestCase;

/**
 * When a header slot holds a single sticky-navbar, PublicPagePresenter promotes
 * it: the slot wrapper becomes the <nav> and the navbar's children render in its
 * place, so the sticky-navbar block itself never renders. That block is the only
 * thing that flushes PublicNavbarDrawerRegistry, so the drawer pushed by
 * navbar-navigation used to be dropped on the floor and the burger pointed at an
 * id that did not exist.
 *
 * The sibling drawer tests render the navbar template directly and therefore
 * never take the promotion path -- this one goes through the presenter and the
 * public layout exactly the way the public page controller does.
 */
class NavbarDrawerPromotedHeaderTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function the_presenter_promotes_a_lone_header_navbar_into_the_slot_wrapper(): void
  {
    $page = $this->seedNavbarPage();
    $presented = app(PublicPagePresenter::class)->present($page);

    $header = collect($presented['slots'] ?? [])->firstWhere('slug', 'header');

    $this->assertNotNull($header, 'No header slot was presented.');
    $this->assertSame('nav', $header['wrapper']['element'], 'Header wrapper should be promoted to <nav>.');
    $this->assertSame(
      'sticky-navbar',
      $header['wrapper']['attributes']['data-wb-public-block-type'] ?? null,
    );

    // Promotion replaces the navbar block with its children, which is exactly
    // why nothing flushes the drawer registry on this path.
    $this->assertNotContains(
      'sticky-navbar',
      collect($header['blocks'])->map(fn (Block $block) => $block->typeSlug())->all(),
    );
  }

  #[Test]
  public function the_drawer_still_renders_when_the_header_navbar_is_promoted(): void
  {
    $html = $this->renderPublicPage();

    $this->assertStringContainsString('wb-navbar-toggle', $html);
    $this->assertSame(1, preg_match('/data-wb-collapse="([^"]+)"/', $html, $m));
    $target = $m[1];

    $this->assertMatchesRegularExpression(
      '/<div class="wb-navbar-drawer" id="'.preg_quote($target, '/').'"/',
      $html,
      "Burger targets #{$target} but no element with that id is rendered.",
    );
  }

  #[Test]
  public function the_promoted_drawer_renders_after_the_navbar_element(): void
  {
    $html = $this->renderPublicPage();

    $navClose = strpos($html, '</nav>');
    $drawerStart = strpos($html, '<div class="wb-navbar-drawer"');

    $this->assertNotFalse($navClose);
    $this->assertNotFalse($drawerStart);
    $this->assertGreaterThan($navClose, $drawerStart, 'Drawer must render after </nav>.');

    // The drawer carries the menu, so links land in both the desktop list and it.
    $this->assertSame(2, substr_count($html, '>Docs</a>'));
  }

  #[Test]
  public function a_header_announcement_does_not_bound_the_sticky_navbar(): void
  {
    $html = $this->renderPublicPage(withAnnouncement: true);

    $announcement = strpos($html, 'Shipping announcement');
    $headerClose = strpos($html, '</header>', $announcement);
    $navbar = strpos($html, '<nav ', $headerClose);
    $navbarClose = strpos($html, '</nav>', $navbar);
    $drawer = strpos($html, '<div class="wb-navbar-drawer"', $navbarClose);

    $this->assertNotFalse($announcement);
    $this->assertNotFalse($headerClose);
    $this->assertNotFalse($navbar);
    $this->assertGreaterThan($announcement, $headerClose);
    $this->assertGreaterThan($headerClose, $navbar, 'Sticky navbar must be a sibling of the short announcement wrapper.');
    $this->assertMatchesRegularExpression('/class="[^"]*\\bwb-navbar\\b[^"]*"/', substr($html, $navbar, $navbarClose - $navbar));
    $this->assertNotFalse($drawer);
    $this->assertGreaterThan($navbarClose, $drawer, 'Drawer must remain immediately after the promoted navbar path.');
  }

  private function renderPublicPage(bool $withAnnouncement = false): string
  {
    $page = $this->seedNavbarPage($withAnnouncement);

    // Mirrors WebBlocks\Cms\Http\Controllers\Public\PageController::renderPage().
    return view(
      'webblocks-cms::public.pages.show',
      app(PublicPagePresenter::class)->present($page),
    )->render();
  }

  private function seedNavbarPage(bool $withAnnouncement = false): Page
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

    $make = function (string $type, ?int $parentId, array $settings = []) use ($page, $slotType) {
      return Block::query()->create([
        'page_id' => $page->id,
        'type' => $type,
        'block_type_id' => BlockType::query()->where('slug', $type)->firstOrFail()->id,
        'parent_id' => $parentId,
        'source_type' => 'static',
        'slot' => $slotType->slug,
        'slot_type_id' => $slotType->id,
        'sort_order' => 0,
        'status' => 'published',
        'settings' => json_encode($settings),
      ]);
    };

    // The shipped "Shared responsive navbar" shape, and the one both public
    // WebBlocks sites use: a single navbar wrapping container > cluster.
    if ($withAnnouncement) {
      $announcement = $make('rich-text', null);
      $announcement->update(['content' => 'Shipping announcement']);
    }

    $navbar = $make('sticky-navbar', null, ['sticky_mode' => 'sticky']);
    $navbar->update(['sort_order' => $withAnnouncement ? 1 : 0]);
    $container = $make('container', $navbar->id, ['width' => 'xl']);
    $cluster = $make('cluster', $container->id, ['alignment' => 'between']);
    $make('navbar-navigation', $cluster->id, ['menu_key' => NavigationItem::MENU_PRIMARY]);

    return $page->fresh();
  }

  private function seedBlockTypes(): void
  {
    foreach ([
      ['slug' => 'sticky-navbar', 'name' => 'Navbar'],
      ['slug' => 'navbar-navigation', 'name' => 'Navbar Navigation'],
      ['slug' => 'container', 'name' => 'Container'],
      ['slug' => 'cluster', 'name' => 'Cluster'],
      ['slug' => 'rich-text', 'name' => 'Rich Text'],
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
