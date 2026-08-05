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
 * NavbarDrawerRenderingTest renders sticky-navbar directly. Production goes
 * through the header slot partial, and nests the navigation under
 * container > cluster the way the shipped "Shared responsive navbar" pattern
 * does. The drawer is pushed from a grandchild block and flushed by the navbar
 * container, so this exercises that path end to end.
 */
class NavbarDrawerSlotPathTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function the_drawer_survives_the_header_slot_path(): void
  {
    $html = $this->renderHeaderSlot(nested: false);

    $this->assertStringContainsString('wb-navbar-toggle', $html);
    $this->assertMatchesRegularExpression('/data-wb-collapse="wb-navbar-drawer-\d+"/', $html);
    $this->assertStringContainsString('<div class="wb-navbar-drawer"', $html);

    $this->assertDrawerTargetExists($html);
  }

  #[Test]
  public function the_drawer_survives_when_navigation_is_nested_under_container_and_cluster(): void
  {
    $html = $this->renderHeaderSlot(nested: true);

    $this->assertMatchesRegularExpression('/data-wb-collapse="wb-navbar-drawer-\d+"/', $html);
    $this->assertStringContainsString('<div class="wb-navbar-drawer"', $html);

    $this->assertDrawerTargetExists($html);
  }

  /** The burger's collapse target must actually exist as an element id. */
  private function assertDrawerTargetExists(string $html): void
  {
    $this->assertSame(1, preg_match('/data-wb-collapse="([^"]+)"/', $html, $m));
    $target = $m[1];

    $this->assertMatchesRegularExpression(
      '/<div class="wb-navbar-drawer" id="'.preg_quote($target, '/').'"/',
      $html,
      "Burger targets #{$target} but no element with that id is rendered.",
    );
  }

  private function renderHeaderSlot(bool $nested): string
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

    $make = function (string $type, ?int $parentId, int $order = 0, array $settings = []) use ($page, $slotType) {
      return Block::query()->create([
        'page_id' => $page->id,
        'type' => $type,
        'block_type_id' => BlockType::query()->where('slug', $type)->firstOrFail()->id,
        'parent_id' => $parentId,
        'source_type' => 'static',
        'slot' => $slotType->slug,
        'slot_type_id' => $slotType->id,
        'sort_order' => $order,
        'status' => 'published',
        'settings' => json_encode($settings),
      ]);
    };

    $navbar = $make('sticky-navbar', null);
    $navParent = $navbar->id;

    if ($nested) {
      $container = $make('container', $navbar->id, 0, ['width' => 'xl']);
      $cluster = $make('cluster', $container->id, 0, ['alignment' => 'between']);
      $navParent = $cluster->id;
    }

    $make('navbar-navigation', $navParent, 0, ['menu_key' => NavigationItem::MENU_PRIMARY]);

    $slot = [
      'slug' => 'header',
      'blocks' => collect([Block::query()->whereKey($navbar->id)->firstOrFail()]),
      'wrapper' => [
        'preset' => 'default',
        'element' => 'header',
        'attributes' => ['data-wb-slot' => 'header'],
        'class' => 'wb-slot-header',
      ],
    ];

    return view('webblocks-cms::pages.partials.slot', [
      'slot' => $slot,
      'page' => $page,
      'renderWrapper' => true,
    ])->render();
  }

  private function seedBlockTypes(): void
  {
    foreach ([
      ['slug' => 'sticky-navbar', 'name' => 'Navbar'],
      ['slug' => 'navbar-navigation', 'name' => 'Navbar Navigation'],
      ['slug' => 'container', 'name' => 'Container'],
      ['slug' => 'cluster', 'name' => 'Cluster'],
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
