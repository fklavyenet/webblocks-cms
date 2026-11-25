<?php

namespace WebBlocks\Cms\Tests\Feature;

use Closure;
use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
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

  #[Test]
  public function a_nested_group_renders_as_a_desktop_submenu_and_a_named_drawer_group(): void
  {
    $html = $this->renderNavbar(configureMenu: function (Page $page): void {
      $products = $this->navigationItem($page, ['title' => 'Products', 'link_type' => NavigationItem::LINK_GROUP]);
      $software = $this->navigationItem($page, [
        'title' => 'Software', 'link_type' => NavigationItem::LINK_GROUP, 'parent_id' => $products->id,
      ]);
      $this->navigationItem($page, ['title' => 'Demo home', 'parent_id' => $software->id, 'url' => '/']);
      $this->navigationItem($page, ['title' => 'Demo docs', 'parent_id' => $software->id, 'url' => '/docs', 'position' => 2]);
    });

    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $root = $xpath->query('//button[@data-wb-toggle="dropdown" and contains(., "Products")]')->item(0);
    $this->assertInstanceOf(DOMElement::class, $root);
    $this->assertStringContainsString('is-active', $root->getAttribute('class'));
    $panel = $document->getElementById(substr($root->getAttribute('data-wb-target'), 1));
    $this->assertInstanceOf(DOMElement::class, $panel);
    $this->assertSame(0, $xpath->query('./a', $panel)->length);

    $trigger = $xpath->query('./button[@data-wb-toggle="submenu" and text()="Software"]', $panel)->item(0);
    $this->assertInstanceOf(DOMElement::class, $trigger);
    $this->assertSame('false', $trigger->getAttribute('aria-expanded'));
    $this->assertStringContainsString('is-active', $trigger->getAttribute('class'));
    $submenu = $xpath->query('following-sibling::*[1]', $trigger)->item(0);
    $this->assertInstanceOf(DOMElement::class, $submenu);
    $this->assertSame($trigger->getAttribute('aria-controls'), $submenu->getAttribute('id'));
    $this->assertSame('wb-dropdown-menu', $submenu->getAttribute('class'));
    $this->assertSame('Demo home', $xpath->evaluate('string(./a[@href="/" and @aria-current="page"])', $submenu));
    $this->assertSame('Demo docs', $xpath->evaluate('string(./a[@href="/docs"])', $submenu));

    $drawer = $xpath->query('//div[@class="wb-navbar-drawer"]')->item(0);
    $this->assertInstanceOf(DOMElement::class, $drawer);
    $drawerGroup = $xpath->query('./div[@role="group"]', $drawer)->item(0);
    $this->assertInstanceOf(DOMElement::class, $drawerGroup);
    $this->assertSame('Software', $document->getElementById($drawerGroup->getAttribute('aria-labelledby'))?->textContent);
    $this->assertSame('Demo home', $xpath->evaluate('string(./a[@href="/" and @aria-current="page"])', $drawerGroup));
    $this->assertSame('Demo docs', $xpath->evaluate('string(./a[@href="/docs"])', $drawerGroup));
    $this->assertSame(0, $xpath->query('.//button[@data-wb-toggle="submenu"]', $drawer)->length);
  }

  #[Test]
  public function groups_without_public_destinations_are_pruned_from_both_menus(): void
  {
    $html = $this->renderNavbar(configureMenu: function (Page $page): void {
      $group = $this->navigationItem($page, ['title' => 'Unavailable products', 'link_type' => NavigationItem::LINK_GROUP]);
      $subgroup = $this->navigationItem($page, ['title' => 'Unavailable software', 'link_type' => NavigationItem::LINK_GROUP, 'parent_id' => $group->id]);
      $this->navigationItem($page, [
        'title' => 'Hidden feature', 'parent_id' => $subgroup->id, 'url' => '/hidden', 'visibility' => NavigationItem::VISIBILITY_HIDDEN,
      ]);
      $hiddenGroup = $this->navigationItem($page, [
        'title' => 'Hidden subgroup', 'link_type' => NavigationItem::LINK_GROUP, 'parent_id' => $group->id, 'visibility' => NavigationItem::VISIBILITY_HIDDEN,
      ]);
      $this->navigationItem($page, ['title' => 'Child of hidden subgroup', 'parent_id' => $hiddenGroup->id, 'url' => '/unlisted']);

      $localeId = Locale::query()->where('code', 'en')->value('id');
      foreach (['draft' => Page::STATUS_DRAFT, 'restricted' => Page::STATUS_PUBLISHED] as $name => $status) {
        $destination = Page::query()->create(['site_id' => $page->site_id, 'status' => $status]);
        PageTranslation::query()->create([
          'page_id' => $destination->id, 'site_id' => $page->site_id, 'locale_id' => $localeId,
          'name' => $name, 'slug' => $name, 'path' => '/'.$name,
        ]);
        if ($status === Page::STATUS_PUBLISHED) {
          $destination->accessRule()->create(['package_id' => null]);
        }
        $this->navigationItem($page, [
          'title' => ucfirst($name).' destination', 'parent_id' => $subgroup->id,
          'link_type' => NavigationItem::LINK_PAGE, 'page_id' => $destination->id,
        ]);
      }
    });

    $this->assertSame(2, substr_count($html, '>Docs</a>'));
    foreach (['Unavailable products', 'Unavailable software', 'Hidden feature', 'Hidden subgroup', 'Child of hidden subgroup', 'Draft destination', 'Restricted destination'] as $label) {
      $this->assertStringNotContainsString($label, $html);
    }
  }

  #[Test]
  public function configured_navigation_icons_render_with_their_labels(): void
  {
    IconCatalogItem::query()->create([
      'source' => 'webblocks-ui',
      'slug' => 'shopping-cart',
      'label' => 'Shopping Cart',
      'css_class' => 'wb-icon-shopping-cart',
      'categories' => ['commerce'],
      'contexts' => ['commerce', 'navigation'],
      'keywords' => ['cart'],
      'is_active' => true,
      'sort_order' => 1,
    ]);

    $html = $this->renderNavbar(icon: 'shopping-cart');

    $this->assertSame(2, substr_count($html, 'wb-icon wb-icon-shopping-cart'));
    $this->assertSame(2, substr_count($html, '<span>Docs</span>'));
  }

  private function navigationItem(Page $page, array $data): NavigationItem
  {
    return NavigationItem::query()->create($data + [
      'site_id' => $page->site_id,
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'link_type' => NavigationItem::LINK_CUSTOM_URL,
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);
  }

  private function renderNavbar(bool $withGroup = false, ?string $icon = null, ?Closure $configureMenu = null): string
  {
    $this->seedBlockTypes();
    [$page, $slotType] = $this->seedPage();

    NavigationItem::query()->create([
      'site_id' => $page->site_id,
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Docs',
      'link_type' => NavigationItem::LINK_CUSTOM_URL,
      'url' => '/docs',
      'icon' => $icon,
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

    $configureMenu?->__invoke($page);

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
