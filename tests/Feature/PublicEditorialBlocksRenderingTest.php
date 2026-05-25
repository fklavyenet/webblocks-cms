<?php

namespace Tests\Feature;

use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Media as Asset;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockTranslationWriter;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PublicEditorialBlocksRenderingTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function canonical_public_block_renderers_exist_for_current_layout_and_content_blocks(): void
  {
    foreach (['header', 'plain_text', 'rich-text', 'section', 'container', 'cluster', 'grid', 'content_header', 'button_link', 'card', 'alert', 'breadcrumb', 'header-actions', 'sticky-navbar', 'navbar-brand', 'navbar-navigation', 'sidebar-brand', 'sidebar-navigation', 'sidebar-nav-item', 'sidebar-nav-group', 'sidebar-footer'] as $slug) {
      $this->assertTrue(View::exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::pages.partials.blocks.'.$slug));
      $this->assertTrue(View::exists('pages.partials.blocks.'.$slug));
    }
  }

  #[Test]
  public function core_public_block_types_now_resolve_package_namespaced_renderers_first(): void
  {
    $this->assertSame(
      'webblocks-cms::pages.partials.blocks.hero',
      (new Block(['type' => 'hero']))->publicRenderView()
    );
    $this->assertSame(
      'webblocks-cms::pages.partials.blocks.columns',
      (new Block(['type' => 'columns']))->publicRenderView()
    );
    $this->assertSame(
      'webblocks-cms::pages.partials.blocks.gallery',
      (new Block(['type' => 'gallery']))->publicRenderView()
    );
  }

  #[Test]
  public function install_specific_root_block_renderers_still_fall_back_safely(): void
  {
    $finder = view()->getFinder();
    $originalPaths = $finder->getPaths();

    $finder->prependLocation(base_path('tests/Fixtures/views'));

    try {
      $block = new Block(['type' => 'test-install-only-renderer']);

      $this->assertSame('pages.partials.blocks.test-install-only-renderer', $block->publicRenderView());
      $this->assertStringContainsString(
        'Test install-only renderer',
        view($block->publicRenderView(), ['block' => $block])->render()
      );
    } finally {
      $finder->setPaths($originalPaths);
    }
  }

  #[Test]
  public function unknown_block_types_still_fall_back_to_a_safe_missing_renderer_view(): void
  {
    $this->assertSame(
      'webblocks-cms::pages.partials.blocks.missing-renderer',
      (new Block(['type' => 'totally-unknown-block']))->publicRenderView()
    );
  }

  #[Test]
  public function navbar_renders_only_wrapper_and_direct_child_blocks(): void
  {
    $page = $this->pageWithMainSlot();
    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sticky-navbar',
      'block_type_id' => $this->blockType('sticky-navbar', 'Navbar', 18, true, true)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode([
        'sticky_mode' => 'sticky',
        'menu_key' => NavigationItem::MENU_PRIMARY,
        'brand_url' => '/',
        'visual_variant' => 'light',
      ], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => true,
    ]);

    $child = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $block->id,
      'type' => 'plain_text',
      'block_type_id' => $this->blockType('plain_text', 'Plain Text', 6)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'content' => 'Inner child',
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<nav class="wb-navbar" data-wb-public-block-type="sticky-navbar">', false);
    $response->assertSee('<p>Inner child</p>', false);
    $response->assertDontSee('wb-cms-navbar--sticky', false);
    $response->assertDontSee('wb-cms-sticky-navbar', false);
    $response->assertDontSee('<div class="wb-container', false);
    $this->assertSame($child->id, $block->children->first()?->id);
  }

  #[Test]
  public function navbar_brand_and_navigation_child_blocks_render_logo_text_menu_items_and_active_state(): void
  {
    $page = $this->pageWithMainSlot();
    $page->site->update(['display_name' => 'Site Brand']);
    $asset = Asset::query()->create([
      'disk' => 'public',
      'path' => 'media/images/sticky-navbar-logo.png',
      'filename' => 'sticky-navbar-logo.png',
      'original_name' => 'sticky-navbar-logo.png',
      'extension' => 'png',
      'mime_type' => 'image/png',
      'size' => 1200,
      'kind' => 'image',
      'visibility' => 'public',
    ]);

    $navbar = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sticky-navbar',
      'block_type_id' => $this->blockType('sticky-navbar', 'Navbar', 18, true, true)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode([
        'sticky_mode' => 'static',
      ], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => true,
    ]);

    $brand = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $navbar->id,
      'type' => 'navbar-brand',
      'block_type_id' => $this->blockType('navbar-brand', 'Navbar Brand', 19)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'asset_id' => $asset->id,
      'settings' => json_encode(['url' => '/', 'target' => '_self'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $brand->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'FKlavye',
      'subtitle' => 'Docs',
    ]);

    $navigation = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $navbar->id,
      'type' => 'navbar-navigation',
      'block_type_id' => $this->blockType('navbar-navigation', 'Navbar Navigation', 20)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 1,
      'settings' => json_encode(['menu_key' => NavigationItem::MENU_PRIMARY], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $navigation->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Primary navigation',
    ]);

    NavigationItem::query()->create([
      'site_id' => $page->site_id,
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'About',
      'link_type' => NavigationItem::LINK_PAGE,
      'page_id' => $page->id,
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    NavigationItem::query()->create([
      'site_id' => $page->site_id,
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Contact',
      'link_type' => NavigationItem::LINK_CUSTOM_URL,
      'url' => '/contact',
      'position' => 2,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<nav class="wb-navbar wb-navbar--static" data-wb-public-block-type="sticky-navbar">', false);
    $response->assertSee('class="wb-navbar-brand"', false);
    $response->assertSee('sticky-navbar-logo.png', false);
    $response->assertSee('FKlavye', false);
    $response->assertSee('Docs', false);
    $response->assertSee('class="wb-cms-navbar-navigation"', false);
    $response->assertSee('class="wb-navbar-toggle wb-cms-navbar-mobile-toggle-button"', false);
    $response->assertSee('class="wb-icon wb-icon-menu" aria-hidden="true"', false);
    $response->assertSee('aria-controls="wb-navbar-navigation-mobile-menu-'.$navigation->id.'"', false);
    $response->assertSee('aria-expanded="false"', false);
    $response->assertSee('aria-label="Toggle navigation"', false);
    $response->assertSee('data-wb-toggle="dropdown"', false);
    $response->assertSee('data-wb-target="#wb-navbar-navigation-mobile-menu-'.$navigation->id.'"', false);
    $response->assertSee('id="wb-navbar-navigation-mobile-menu-'.$navigation->id.'"', false);
    $response->assertSee('class="wb-navbar-links"', false);
    $response->assertSee('href="/p/about" class="wb-navbar-link is-active" aria-current="page"', false);
    $response->assertSee('href="/contact" class="wb-navbar-link"', false);
    $response->assertSee('href="/contact" class="wb-dropdown-item"', false);
    $response->assertDontSee('<span></span><span></span><span></span>', false);
    $response->assertDontSee('wb-cms-sticky-navbar', false);
  }

  #[Test]
  public function navbar_composition_supports_neutral_container_cluster_and_logo_only_brand(): void
  {
    $page = $this->pageWithMainSlot();
    $page->site->update(['display_name' => 'Site Brand']);

    $asset = Asset::query()->create([
      'disk' => 'public',
      'path' => 'media/images/navbar-logo-only.png',
      'filename' => 'navbar-logo-only.png',
      'original_name' => 'navbar-logo-only.png',
      'extension' => 'png',
      'mime_type' => 'image/png',
      'size' => 1200,
      'kind' => 'image',
      'visibility' => 'public',
    ]);

    $navbar = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sticky-navbar',
      'block_type_id' => $this->blockType('sticky-navbar', 'Navbar', 18, true, true)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['sticky_mode' => 'static'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => true,
    ]);

    $container = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $navbar->id,
      'type' => 'container',
      'block_type_id' => $this->blockType('container', 'Container', 5, false, true)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['flow' => 'none'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $cluster = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'cluster',
      'block_type_id' => $this->blockType('cluster', 'Cluster', 6, false, true)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode([
        'width' => 'full',
        'alignment' => 'between',
        'wrap' => 'nowrap',
      ], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $rightCluster = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $cluster->id,
      'type' => 'cluster',
      'block_type_id' => $this->blockType('cluster', 'Cluster', 6, false, true)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 1,
      'settings' => json_encode([
        'alignment' => 'end',
        'wrap' => 'nowrap',
        'gap' => 'sm',
      ], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $brand = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $cluster->id,
      'type' => 'navbar-brand',
      'block_type_id' => $this->blockType('navbar-brand', 'Navbar Brand', 19)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'asset_id' => $asset->id,
      'settings' => json_encode(['url' => '/', 'target' => '_self', 'aria_label' => 'Fklavye Web Services'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $brand->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => '',
      'subtitle' => '',
    ]);

    $navigation = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $rightCluster->id,
      'type' => 'navbar-navigation',
      'block_type_id' => $this->blockType('navbar-navigation', 'Navbar Navigation', 20)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 1,
      'settings' => json_encode(['menu_key' => NavigationItem::MENU_PRIMARY], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $navigation->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Primary navigation',
    ]);

    $actions = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $rightCluster->id,
      'type' => 'header-actions',
      'block_type_id' => $this->blockType('header-actions', 'Header Actions', 21)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 2,
      'settings' => json_encode(['show_mode_toggle' => true, 'show_accent_toggle' => false, 'show_search' => false], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    NavigationItem::query()->create([
      'site_id' => $page->site_id,
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'About',
      'link_type' => NavigationItem::LINK_PAGE,
      'page_id' => $page->id,
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<nav class="wb-navbar wb-navbar--static" data-wb-public-block-type="sticky-navbar">', false);
    $response->assertDontSee('<nav class="wb-navbar wb-navbar--static"><div class="wb-container', false);
    $response->assertSee('<div class="wb-container" data-wb-public-block-type="container">', false);
    $response->assertDontSee('<div class="wb-container wb-stack" data-wb-public-block-type="container">', false);
    $response->assertSee('<div class="wb-cluster wb-cluster-between wb-flex-nowrap wb-w-full" data-wb-public-block-type="cluster">', false);
    $response->assertSee('<div class="wb-cluster wb-cluster-2 wb-cluster-end wb-flex-nowrap" data-wb-public-block-type="cluster">', false);
    $response->assertSee('aria-label="Fklavye Web Services"', false);
    $response->assertSee('alt="Fklavye Web Services"', false);
    $response->assertSee('navbar-logo-only.png', false);
    $response->assertDontSee('<span class="wb-navbar-identity">', false);
    $response->assertSee('class="wb-navbar-links"', false);
    $response->assertSee('aria-label="Auto mode"', false);
    $response->assertDontSee('wb-cms-navbar-row', false);
    $response->assertSeeInOrder([
      '<nav class="wb-navbar wb-navbar--static" data-wb-public-block-type="sticky-navbar">',
      '<div class="wb-container" data-wb-public-block-type="container">',
      '<div class="wb-cluster wb-cluster-between wb-flex-nowrap wb-w-full" data-wb-public-block-type="cluster">',
      'class="wb-navbar-brand"',
      '<div class="wb-cluster wb-cluster-2 wb-cluster-end wb-flex-nowrap" data-wb-public-block-type="cluster">',
      'class="wb-navbar-links"',
      'data-wb-header-actions',
    ], false);

    unset($actions);
  }

  #[Test]
  public function navbar_brand_without_saved_url_falls_back_to_the_resolved_site_home_path(): void
  {
    $page = $this->pageWithMainSlot();
    $navbar = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sticky-navbar',
      'block_type_id' => $this->blockType('sticky-navbar', 'Navbar', 18, true, true)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['sticky_mode' => 'static'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => true,
    ]);

    $brand = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $navbar->id,
      'type' => 'navbar-brand',
      'block_type_id' => $this->blockType('navbar-brand', 'Navbar Brand', 19)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $brand->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Home',
    ]);

    $expectedHomePath = app(PageRouteResolver::class)->homePath($brand->renderLocaleCode(), $page->site) ?? '/';

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<a href="'.$expectedHomePath.'" class="wb-navbar-brand"', false);
  }

  #[Test]
  public function sidebar_navigation_renders_only_sidebar_nav_wrapper_with_section_and_children(): void
  {
    $page = $this->pageWithMainSlot();
    $nav = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sidebar-navigation',
      'block_type_id' => $this->blockType('sidebar-navigation', 'Sidebar Navigation', 16)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $nav->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Documentation navigation',
    ]);

    $item = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $nav->id,
      'type' => 'sidebar-nav-item',
      'block_type_id' => $this->blockType('sidebar-nav-item', 'Sidebar Nav Item', 17)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['url' => '/p/about', 'target' => '_self'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $item->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Getting Started',
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<nav class="wb-sidebar-nav" aria-label="Documentation navigation">', false);
    $response->assertSee('<div class="wb-sidebar-section">', false);
    $response->assertSee('class="wb-sidebar-link is-active"', false);
    $response->assertSee('href="/p/about"', false);
    $response->assertSee('aria-current="page"', false);
    $response->assertDontSee('<div class="wb-sidebar-nav"', false);
  }

  #[Test]
  public function sidebar_navigation_can_render_a_selected_navigation_menu_with_icons_groups_and_active_links(): void
  {
    $page = $this->pageWithMainSlot();
    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sidebar-navigation',
      'block_type_id' => $this->blockType('sidebar-navigation', 'Sidebar Navigation', 16)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode([
        'menu_key' => NavigationItem::MENU_PRIMARY,
        'show_icons' => true,
        'active_matching' => 'current-page',
      ], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $block->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Documentation navigation',
    ]);

    $group = NavigationItem::query()->create([
      'site_id' => $page->site_id,
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Guides',
      'link_type' => NavigationItem::LINK_GROUP,
      'icon' => 'layers',
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    NavigationItem::query()->create([
      'site_id' => $page->site_id,
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'parent_id' => $group->id,
      'title' => 'Getting Started',
      'link_type' => NavigationItem::LINK_PAGE,
      'page_id' => $page->id,
      'icon' => 'rocket',
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    NavigationItem::query()->create([
      'site_id' => $page->site_id,
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'parent_id' => $group->id,
      'title' => 'External Docs',
      'link_type' => NavigationItem::LINK_CUSTOM_URL,
      'url' => 'https://example.com/docs',
      'target' => '_blank',
      'icon' => 'code',
      'position' => 2,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    NavigationItem::query()->create([
      'site_id' => $page->site_id,
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Overview',
      'link_type' => NavigationItem::LINK_PAGE,
      'page_id' => $page->id,
      'icon' => 'home',
      'position' => 2,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<nav class="wb-sidebar-nav" aria-label="Documentation navigation">', false);
    $response->assertSee('<div class="wb-nav-group is-open" data-wb-nav-group>', false);
    $response->assertSee('aria-expanded="true"', false);
    $response->assertSee('data-wb-nav-group-toggle', false);
    $response->assertSee('aria-controls="wb-nav-group-items-'.$group->id.'"', false);
    $response->assertSee('class="wb-nav-group-items"', false);
    $response->assertSee('id="wb-nav-group-items-'.$group->id.'"', false);
    $response->assertSee('hidden', false);
    $response->assertSee('Guides', false);
    $response->assertSee('wb-icon-layers', false);
    $response->assertSee('wb-icon-rocket', false);
    $response->assertSee('wb-icon-home', false);
    $response->assertSee('href="/p/about" class="wb-nav-group-item is-active" aria-current="page"', false);
    $response->assertSee('href="https://example.com/docs"', false);
    $response->assertSee('class="wb-nav-group-item"', false);
    $response->assertSee('target="_blank" rel="noopener noreferrer"', false);
    $response->assertSee('href="/p/about" class="wb-sidebar-link is-active" aria-current="page"', false);
    $response->assertSee('cms/js/public/sidebar-navigation.js', false);
  }

  #[Test]
  public function docs_sidebar_group_opens_when_a_child_page_is_active(): void
  {
    $page = $this->pageWithMainSlot('Overview', 'overview');
    $childPage = $this->pageWithMainSlot('Dashboard Shell', 'dashboard-shell');
    $page->update(['settings' => ['public_shell' => 'docs']]);
    $childPage->update(['settings' => ['public_shell' => 'docs']]);

    $block = Block::query()->create([
      'page_id' => $childPage->id,
      'type' => 'sidebar-navigation',
      'block_type_id' => $this->blockType('sidebar-navigation', 'Sidebar Navigation', 16)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode([
        'menu_key' => NavigationItem::MENU_DOCS,
        'show_icons' => false,
        'active_matching' => 'current-page',
      ], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $block->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Documentation navigation',
    ]);

    $patterns = NavigationItem::query()->create([
      'site_id' => $childPage->site_id,
      'menu_key' => NavigationItem::MENU_DOCS,
      'title' => 'Patterns',
      'link_type' => NavigationItem::LINK_GROUP,
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    NavigationItem::query()->create([
      'site_id' => $childPage->site_id,
      'menu_key' => NavigationItem::MENU_DOCS,
      'parent_id' => $patterns->id,
      'title' => 'Overview',
      'link_type' => NavigationItem::LINK_PAGE,
      'page_id' => $page->id,
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    NavigationItem::query()->create([
      'site_id' => $childPage->site_id,
      'menu_key' => NavigationItem::MENU_DOCS,
      'parent_id' => $patterns->id,
      'title' => 'Dashboard Shell',
      'link_type' => NavigationItem::LINK_PAGE,
      'page_id' => $childPage->id,
      'position' => 2,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $response = $this->get(route('pages.show', 'dashboard-shell'));

    $response->assertOk();
    $response->assertSee('<div class="wb-nav-group is-open" data-wb-nav-group>', false);
    $response->assertSee('aria-expanded="true"', false);
    $response->assertSee('aria-controls="wb-nav-group-items-'.$patterns->id.'"', false);
    $response->assertSee('class="wb-nav-group-items"', false);
    $response->assertSee('id="wb-nav-group-items-'.$patterns->id.'"', false);
    $response->assertDontSee('id="wb-nav-group-items-'.$patterns->id.'" hidden', false);
    $response->assertSee('href="/p/dashboard-shell" class="wb-nav-group-item is-active" aria-current="page"', false);
    $response->assertSee('href="/p/overview" class="wb-nav-group-item"', false);
  }

  #[Test]
  public function public_sidebar_navigation_script_defers_click_toggling_to_webblocks_ui_and_only_syncs_hidden_state(): void
  {
    $script = file_get_contents(public_path('cms/js/public/sidebar-navigation.js'));

    $this->assertIsString($script);
    $this->assertStringNotContainsString("addEventListener('click'", $script);
    $this->assertStringContainsString("addEventListener('wb:navgroup:open'", $script);
    $this->assertStringContainsString("addEventListener('wb:navgroup:close'", $script);
    $this->assertStringContainsString("items.hidden = !group.classList.contains('is-open');", $script);
  }

  #[Test]
  public function sidebar_navigation_with_selected_empty_menu_renders_no_wrapper(): void
  {
    $page = $this->pageWithMainSlot();
    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sidebar-navigation',
      'block_type_id' => $this->blockType('sidebar-navigation', 'Sidebar Navigation', 16)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode([
        'menu_key' => NavigationItem::MENU_PRIMARY,
        'show_icons' => false,
      ], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $block->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Documentation navigation',
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertDontSee('<nav class="wb-sidebar-nav"', false);
  }

  #[Test]
  public function sidebar_brand_renders_logo_and_copy_with_webblocks_contract(): void
  {
    $page = $this->pageWithMainSlot();
    $asset = Asset::query()->create([
      'disk' => 'public',
      'path' => 'media/images/webblocks-ui-logo.png',
      'filename' => 'webblocks-ui-logo.png',
      'original_name' => 'webblocks-ui-logo.png',
      'extension' => 'png',
      'mime_type' => 'image/png',
      'size' => 1234,
      'kind' => 'image',
      'visibility' => 'public',
    ]);

    $brand = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sidebar-brand',
      'block_type_id' => $this->blockType('sidebar-brand', 'Sidebar Brand', 15)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'asset_id' => $asset->id,
      'settings' => json_encode(['url' => 'https://example.com', 'target' => '_blank'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $brand->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'WebBlocks UI',
      'subtitle' => 'UI building blocks for humans and AI',
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('href="https://example.com"', false);
    $response->assertSee('class="wb-sidebar-brand"', false);
    $response->assertSee('target="_blank" rel="noopener noreferrer"', false);
    $response->assertSee('class="wb-sidebar-brand-logo"', false);
    $response->assertSee('webblocks-ui-logo.png', false);
    $response->assertSee('alt="WebBlocks UI"', false);
    $response->assertSee('<span class="wb-sidebar-brand-copy">', false);
    $response->assertSee('<span>WebBlocks UI</span>', false);
    $response->assertSee('<span class="wb-sidebar-brand-note">UI building blocks for humans and AI</span>', false);
  }

  #[Test]
  public function sidebar_brand_does_not_render_empty_logo_image_when_logo_is_missing(): void
  {
    $page = $this->pageWithMainSlot();
    $brand = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sidebar-brand',
      'block_type_id' => $this->blockType('sidebar-brand', 'Sidebar Brand', 15)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['url' => '/'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $brand->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Docs Home',
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('href="/"', false);
    $response->assertSee('class="wb-sidebar-brand"', false);
    $response->assertDontSee('wb-sidebar-brand-logo', false);
    $response->assertSee('<span class="wb-sidebar-brand-copy">', false);
    $response->assertSee('<span>Docs Home</span>', false);
  }

  #[Test]
  public function sidebar_brand_logo_only_uses_accessible_label_then_site_fallback_without_forcing_visible_text(): void
  {
    $page = $this->pageWithMainSlot();
    $page->site->update(['display_name' => 'Docs Site']);
    $asset = Asset::query()->create([
      'disk' => 'public',
      'path' => 'media/images/sidebar-brand-logo-only.png',
      'filename' => 'sidebar-brand-logo-only.png',
      'original_name' => 'sidebar-brand-logo-only.png',
      'extension' => 'png',
      'mime_type' => 'image/png',
      'size' => 1234,
      'kind' => 'image',
      'visibility' => 'public',
    ]);

    $explicitLabelBrand = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sidebar-brand',
      'block_type_id' => $this->blockType('sidebar-brand', 'Sidebar Brand', 15)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'asset_id' => $asset->id,
      'settings' => json_encode(['aria_label' => 'Knowledge Base Home'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $explicitLabelBrand->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => '',
      'subtitle' => '',
    ]);

    $siteFallbackBrand = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sidebar-brand',
      'block_type_id' => $this->blockType('sidebar-brand', 'Sidebar Brand', 15)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 1,
      'asset_id' => $asset->id,
      'status' => 'published',
      'is_system' => false,
    ]);
    $siteFallbackBrand->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => '',
      'subtitle' => '',
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('aria-label="Knowledge Base Home"', false);
    $response->assertSee('alt="Knowledge Base Home"', false);
    $response->assertSee('aria-label="Docs Site"', false);
    $response->assertSee('alt="Docs Site"', false);
    $response->assertDontSee('<span class="wb-sidebar-brand-copy">', false);
  }

  #[Test]
  public function manual_sidebar_nav_group_children_reuse_sidebar_item_output_semantics(): void
  {
    $page = $this->pageWithMainSlot();

    $group = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sidebar-nav-group',
      'block_type_id' => $this->blockType('sidebar-nav-group', 'Sidebar Nav Group', 18, false, true)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['icon' => 'layers', 'initially_open' => false], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $group->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Patterns',
    ]);

    $child = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $group->id,
      'type' => 'sidebar-nav-item',
      'block_type_id' => $this->blockType('sidebar-nav-item', 'Sidebar Nav Item', 17)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['url' => '/p/about', 'target' => '_blank', 'icon' => 'rocket', 'active_mode' => 'path'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $child->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Getting Started',
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<div class="wb-nav-group is-open" data-wb-nav-group>', false);
    $response->assertSee('aria-controls="wb-nav-group-items-'.$group->id.'"', false);
    $response->assertSee('id="wb-nav-group-items-'.$group->id.'"', false);
    $response->assertSee('class="wb-icon wb-icon-rocket wb-sidebar-icon"', false);
    $response->assertSee('href="/p/about"', false);
    $response->assertSee('class="wb-nav-group-item is-active"', false);
    $response->assertSee('aria-current="page"', false);
    $response->assertSee('target="_blank" rel="noopener noreferrer"', false);
    $response->assertSee('>Getting Started</span>', false);
  }

  #[Test]
  public function sidebar_nav_item_renders_active_link_optional_icon_and_blank_target_safely(): void
  {
    $page = $this->pageWithMainSlot();

    $currentItem = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sidebar-nav-item',
      'block_type_id' => $this->blockType('sidebar-nav-item', 'Sidebar Nav Item', 17)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['url' => '/p/about', 'target' => '_self', 'icon' => 'rocket', 'active_mode' => 'path'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $currentItem->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Launch',
    ]);

    $blankItem = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sidebar-nav-item',
      'block_type_id' => $this->blockType('sidebar-nav-item', 'Sidebar Nav Item', 17)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 1,
      'settings' => json_encode(['url' => 'https://example.com/docs', 'target' => '_blank', 'active_mode' => 'manual', 'manual_active' => false], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $blankItem->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'External Docs',
    ]);

    $iconlessItem = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sidebar-nav-item',
      'block_type_id' => $this->blockType('sidebar-nav-item', 'Sidebar Nav Item', 17)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 2,
      'settings' => json_encode(['url' => '/p/about', 'target' => '_self'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $iconlessItem->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Plain Link',
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('class="wb-sidebar-link is-active"', false);
    $response->assertSee('href="/p/about"', false);
    $response->assertSee('aria-current="page"', false);
    $response->assertSee('class="wb-icon wb-icon-rocket wb-sidebar-icon"', false);
    $response->assertSee('href="https://example.com/docs"', false);
    $response->assertSee('target="_blank"', false);
    $response->assertSee('rel="noopener noreferrer"', false);
    $response->assertSee('>Plain Link</span>', false);
    $response->assertDontSee('<i class="wb-icon wb-icon-"', false);
  }

  #[Test]
  public function sidebar_footer_renders_callout_and_version_text_from_translation_rows(): void
  {
    $page = $this->pageWithMainSlot();
    $footer = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sidebar-footer',
      'block_type_id' => $this->blockType('sidebar-footer', 'Sidebar Footer', 19)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['variant' => 'info'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $footer->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Need help?',
      'content' => 'Open the starter guide first.',
      'subtitle' => 'WebBlocks UI v2.4.4',
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<div class="wb-sidebar-footer">', false);
    $response->assertSee('<div class="wb-callout wb-callout-info">', false);
    $response->assertSee('<div class="wb-callout-title">Need help?</div>', false);
    $response->assertSee('<p>Open the starter guide first.</p>', false);
    $response->assertSee('<p class="wb-text-xs wb-text-muted wb-mt-3 wb-mb-0">WebBlocks UI v2.4.4</p>', false);
  }

  #[Test]
  public function header_actions_renders_theme_buttons_without_inline_script(): void
  {
    $page = $this->pageWithMainSlot();

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'header-actions',
      'block_type_id' => $this->blockType('header-actions', 'Header Actions', 14, true)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['show_mode_toggle' => true, 'show_accent_toggle' => true], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => true,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('data-wb-header-actions', false);
    $response->assertSee('data-wb-mode-cycle', false);
    $response->assertSee('data-wb-header-actions-mode-toggle', false);
    $response->assertSee('data-wb-header-actions-accent-toggle', false);
    $response->assertSee('data-wb-header-actions-preset-option', false);
    $response->assertSee('data-wb-public-search-open', false);
    $response->assertSee('data-wb-toggle="dropdown"', false);
    $response->assertSee('class="wb-dropdown-label">Presets</div>', false);
    $response->assertSee('data-wb-preset-set="modern"', false);
    $response->assertSee('data-wb-preset-set="corporate"', false);
    $response->assertSee('data-wb-preset-set="minimal"', false);
    $response->assertSee('data-wb-preset-set="editorial"', false);
    $response->assertSee('data-wb-preset-set="playful"', false);
    $response->assertSee('Modern', false);
    $response->assertSee('Corporate', false);
    $response->assertSee('Minimal', false);
    $response->assertSee('Editorial', false);
    $response->assertSee('Playful', false);
    $response->assertSee('class="wb-dropdown-label">Accent</div>', false);
    $response->assertSee('data-wb-accent-set="ocean"', false);
    $response->assertSee('data-wb-accent-set="royal"', false);
    $response->assertSee('data-wb-accent-set="forest"', false);
    $response->assertSee('data-wb-accent-set="sunset"', false);
    $response->assertSee('data-wb-accent-set="mint"', false);
    $response->assertSee('data-wb-accent-set="amber"', false);
    $response->assertSee('data-wb-accent-set="rose"', false);
    $response->assertSee('data-wb-accent-set="slate-fire"', false);
    $response->assertSee('Ocean', false);
    $response->assertSee('Royal', false);
    $response->assertSee('Forest', false);
    $response->assertSee('Sunset', false);
    $response->assertSee('Mint', false);
    $response->assertSee('Amber', false);
    $response->assertSee('Rose', false);
    $response->assertSee('Slate Fire', false);
    $response->assertSee('type="button"', false);
    $response->assertSee('aria-pressed="false"', false);
    $response->assertSee('<i class="wb-icon wb-icon-sun-moon" aria-hidden="true"></i>', false);
    $response->assertSee('<i class="wb-icon wb-icon-palette" aria-hidden="true"></i>', false);
    $response->assertSee('<i class="wb-icon wb-icon-search" aria-hidden="true"></i>', false);
    $response->assertSee('aria-label="Auto mode"', false);
    $response->assertSee('aria-label="Theme settings"', false);
    $response->assertSee('aria-label="Search"', false);
    $response->assertSee('href="/search"', false);
    $response->assertSee('aria-expanded="false"', false);
    $response->assertSee('aria-haspopup="menu"', false);
    $response->assertSee('aria-controls="wb-header-actions-theme-menu-', false);
    $response->assertDontSee('onclick=', false);
    $response->assertDontSee('onchange=', false);
    $response->assertDontSee('javascript:', false);
  }

  #[Test]
  public function header_actions_hides_disabled_controls_and_renders_nothing_when_both_are_disabled(): void
  {
    $page = $this->pageWithMainSlot();

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'header-actions',
      'block_type_id' => $this->blockType('header-actions', 'Header Actions', 14, true)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['show_mode_toggle' => false, 'show_accent_toggle' => true], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => true,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertDontSee('data-wb-header-actions-mode-toggle', false);
    $response->assertSee('data-wb-header-actions-accent-toggle', false);
    $response->assertSee('data-wb-public-search-open', false);

    Block::query()->where('page_id', $page->id)->where('type', 'header-actions')->delete();

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'header-actions',
      'block_type_id' => $this->blockType('header-actions', 'Header Actions', 14, true)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['show_mode_toggle' => false, 'show_accent_toggle' => false, 'show_search' => false], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => true,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertDontSee('data-wb-header-actions', false);
    $response->assertDontSee('data-wb-header-actions-mode-toggle', false);
    $response->assertDontSee('data-wb-header-actions-accent-toggle', false);
    $response->assertDontSee('data-wb-public-search-open', false);
  }

  #[Test]
  public function public_layout_includes_search_modal_markup_and_public_assets(): void
  {
    $page = $this->pageWithMainSlot();

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'header-actions',
      'block_type_id' => $this->blockType('header-actions', 'Header Actions', 14, true)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['show_mode_toggle' => false, 'show_accent_toggle' => false, 'show_search' => true], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => true,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('id="wb-overlay-root"', false);
    $response->assertDontSee('id="wb-public-overlay-root"', false);
    $response->assertDontSee('id="public-overlay-root"', false);
    $response->assertDontSee('id="overlay-root"', false);
    $response->assertSee('data-wb-public-search-overlay', false);
    $response->assertSee('id="wb-public-search-modal"', false);
    $response->assertSee('data-search-json-path="/search.json"', false);
    $response->assertSee('cms/js/public/public-search-modal.js', false);
    $response->assertSee('cms/js/public/sidebar-navigation.js', false);
    $response->assertDontSee('cms/js/public/header-actions.js', false);
    $this->assertSame(1, substr_count($response->getContent(), 'class="wb-overlay-root"'));
    $this->assertSame(1, substr_count($response->getContent(), 'cms/js/public/sidebar-navigation.js'));
    $response->assertSeeInOrder([
      '<head>',
      WebBlocks::uiJsUrl(),
      'cms/js/public/public-search-modal.js',
      'cms/js/public/sidebar-navigation.js',
      '</head>',
    ], false);
  }

  #[Test]
  public function navbar_mobile_toggle_uses_existing_webblocks_ui_dropdown_contract_without_extra_cms_script(): void
  {
    $this->pageWithMainSlot();

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertDontSee('cms/js/public/navbar-toggle.js', false);
  }

  #[Test]
  public function breadcrumb_uses_the_dedicated_public_renderer_with_semantic_markup(): void
  {
    $page = $this->pageWithMainSlot();

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'breadcrumb',
      'block_type_id' => $this->blockType('breadcrumb', 'Breadcrumb', 13, true)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['home_label' => 'Home', 'include_current' => true], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => true,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<nav class="wb-breadcrumb" aria-label="Breadcrumb">', false);
    $response->assertSee('<ol class="wb-breadcrumb-list">', false);
    $response->assertSee('<a class="wb-breadcrumb-link" href="/">Home</a>', false);
    $response->assertSee('<span class="wb-breadcrumb-current" aria-current="page">About</span>', false);
    $response->assertDontSee('<ol class="wb-cluster wb-cluster-2 wb-text-sm">', false);
  }

  #[Test]
  public function breadcrumb_respects_translated_page_names_for_localized_routes(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);

    $site = Site::query()->firstOrFail();
    $turkish = Locale::query()->updateOrCreate(
      ['code' => 'tr'],
      ['name' => 'Turkish', 'is_default' => false, 'is_enabled' => true],
    );
    $site->locales()->syncWithoutDetaching([$turkish->id]);

    $home = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Home',
      'slug' => 'home',
      'page_type' => 'default',
      'status' => 'published',
    ]);
    $about = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'About',
      'slug' => 'about',
      'page_type' => 'default',
      'status' => 'published',
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $home->id, 'locale_id' => Page::defaultLocaleId()],
      ['site_id' => $site->id, 'name' => 'Home', 'slug' => 'home', 'path' => '/'],
    );
    PageTranslation::query()->updateOrCreate(
      ['page_id' => $home->id, 'locale_id' => $turkish->id],
      ['site_id' => $site->id, 'name' => 'Ana Sayfa', 'slug' => 'home', 'path' => '/'],
    );
    PageTranslation::query()->updateOrCreate(
      ['page_id' => $about->id, 'locale_id' => Page::defaultLocaleId()],
      ['site_id' => $site->id, 'name' => 'About', 'slug' => 'about', 'path' => '/p/about'],
    );
    PageTranslation::query()->updateOrCreate(
      ['page_id' => $about->id, 'locale_id' => $turkish->id],
      ['site_id' => $site->id, 'name' => 'Hakkinda', 'slug' => 'hakkinda', 'path' => '/p/hakkinda'],
    );

    PageSlot::query()->create([
      'page_id' => $about->id,
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
    ]);

    Block::query()->create([
      'page_id' => $about->id,
      'type' => 'breadcrumb',
      'block_type_id' => $this->blockType('breadcrumb', 'Breadcrumb', 13, true)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['include_current' => true], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => true,
    ]);

    $defaultResponse = $this->get('/p/about');
    $turkishResponse = $this->get('/tr/p/hakkinda');
    $turkishHomePath = app(PageRouteResolver::class)->homePath('tr', $site);

    $defaultResponse->assertOk();
    $defaultResponse->assertSee('<a class="wb-breadcrumb-link" href="/">Home</a>', false);
    $defaultResponse->assertSee('<span class="wb-breadcrumb-current" aria-current="page">About</span>', false);

    $turkishResponse->assertOk();
    $turkishResponse->assertSee('<a class="wb-breadcrumb-link" href="'.$turkishHomePath.'">Ana Sayfa</a>', false);
    $turkishResponse->assertSee('<span class="wb-breadcrumb-current" aria-current="page">Hakkinda</span>', false);
  }

  #[Test]
  public function default_shell_preserves_plain_slot_wrappers_without_docs_shell_classes(): void
  {
    $page = $this->pageWithMainSlot();

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'plain_text',
      'block_type_id' => $this->blockType('plain_text', 'Plain Text', 2)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ])->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'content' => 'Plain shell content',
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<main data-wb-slot="main" id="main-content">', false);
    $response->assertDontSee('wb-docs-shell', false);
    $response->assertDontSee('wb-docs-content', false);
  }

  #[Test]
  public function default_shell_ignores_saved_wrapper_settings_and_keeps_semantic_slot_mapping(): void
  {
    $page = $this->pageWithMainSlot();

    $slot = $page->slots()->firstOrFail();
    $slot->update([
      'settings' => [
        'wrapper_element' => 'section',
        'wrapper_preset' => 'plain',
      ],
    ]);
    $this->assertNull($slot->fresh()->settings);

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'plain_text',
      'block_type_id' => $this->blockType('plain_text', 'Plain Text', 2)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ])->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'content' => 'Section wrapper content',
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<main data-wb-slot="main" id="main-content">', false);
    $response->assertDontSee('<section data-wb-slot="main" id="main-content">', false);
    $response->assertDontSee('wb-dashboard-main', false);
  }

  #[Test]
  public function docs_shell_header_uses_docs_nav_wrapper_even_when_saved_settings_match_or_conflict(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $site = Site::query()->firstOrFail();
    $headerType = SlotType::query()->updateOrCreate(['slug' => 'header'], ['name' => 'Header', 'status' => 'published', 'sort_order' => 1, 'is_system' => true]);

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Docs Header Test',
      'slug' => 'docs-header-test',
      'page_type' => 'default',
      'status' => 'published',
      'settings' => ['public_shell' => 'docs'],
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
      ['site_id' => $site->id, 'name' => 'Docs Header Test', 'slug' => 'docs-header-test', 'path' => '/p/docs-header-test'],
    );

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $headerType->id,
      'sort_order' => 0,
      'settings' => ['wrapper_preset' => 'docs-navbar', 'wrapper_element' => 'nav'],
    ]);
    $this->assertNull($page->slots()->first()->settings);

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'plain_text',
      'block_type_id' => $this->blockType('plain_text', 'Plain Text', 2)->id,
      'source_type' => 'static',
      'slot' => 'header',
      'slot_type_id' => $headerType->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ])->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'content' => 'Docs nav content',
    ]);

    $response = $this->get('/p/docs-header-test');

    $response->assertOk();
    $response->assertSee('<nav data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full">', false);
    $response->assertDontSee('<header data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full">', false);
  }

  #[Test]
  public function default_shell_ignores_docs_oriented_saved_wrapper_settings(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $site = Site::query()->firstOrFail();
    $sidebarType = SlotType::query()->updateOrCreate(['slug' => 'sidebar'], ['name' => 'Sidebar', 'status' => 'published', 'sort_order' => 1, 'is_system' => true]);

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Default Shell Sidebar Test',
      'slug' => 'default-shell-sidebar-test',
      'page_type' => 'default',
      'status' => 'published',
      'settings' => ['public_shell' => 'default'],
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
      ['site_id' => $site->id, 'name' => 'Default Shell Sidebar Test', 'slug' => 'default-shell-sidebar-test', 'path' => '/p/default-shell-sidebar-test'],
    );

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $sidebarType->id,
      'sort_order' => 0,
      'settings' => ['wrapper_preset' => 'docs-sidebar', 'wrapper_element' => 'aside'],
    ]);
    $this->assertNull($page->slots()->first()->settings);

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'plain_text',
      'block_type_id' => $this->blockType('plain_text', 'Plain Text', 2)->id,
      'source_type' => 'static',
      'slot' => 'sidebar',
      'slot_type_id' => $sidebarType->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ])->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'content' => 'Default shell sidebar content',
    ]);

    $response = $this->get('/p/default-shell-sidebar-test');

    $response->assertOk();
    $response->assertSee('<aside data-wb-slot="sidebar">', false);
    $response->assertDontSee('id="docsSidebar"', false);
    $response->assertDontSee('class="wb-sidebar"', false);
    $response->assertDontSee('<div class="wb-dashboard-shell">', false);
  }

  #[Test]
  public function docs_shell_ignores_bad_saved_wrapper_combination_for_header_slot(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $site = Site::query()->firstOrFail();
    $headerType = SlotType::query()->updateOrCreate(['slug' => 'header'], ['name' => 'Header', 'status' => 'published', 'sort_order' => 1, 'is_system' => true]);

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Docs Header Legacy Settings Test',
      'slug' => 'docs-header-legacy-settings-test',
      'page_type' => 'default',
      'status' => 'published',
      'settings' => ['public_shell' => 'docs'],
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
      ['site_id' => $site->id, 'name' => 'Docs Header Legacy Settings Test', 'slug' => 'docs-header-legacy-settings-test', 'path' => '/p/docs-header-legacy-settings-test'],
    );

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $headerType->id,
      'sort_order' => 0,
      'settings' => ['wrapper_preset' => 'docs-main', 'wrapper_element' => 'main'],
    ]);
    $this->assertNull($page->slots()->first()->settings);

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'plain_text',
      'block_type_id' => $this->blockType('plain_text', 'Plain Text', 2)->id,
      'source_type' => 'static',
      'slot' => 'header',
      'slot_type_id' => $headerType->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ])->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'content' => 'Legacy docs header content',
    ]);

    $response = $this->get('/p/docs-header-legacy-settings-test');

    $response->assertOk();
    $response->assertSee('<nav data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full">', false);
    $response->assertDontSee('<main data-wb-slot="header"', false);
    $response->assertDontSee('class="wb-dashboard-main"', false);
  }

  #[Test]
  public function docs_shell_renders_semantic_slot_order_and_safe_slot_presets(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $site = Site::query()->firstOrFail();
    $headerType = SlotType::query()->updateOrCreate(['slug' => 'header'], ['name' => 'Header', 'status' => 'published', 'sort_order' => 1, 'is_system' => true]);
    $mainType = $this->mainSlotType();
    $sidebarType = SlotType::query()->updateOrCreate(['slug' => 'sidebar'], ['name' => 'Sidebar', 'status' => 'published', 'sort_order' => 3, 'is_system' => true]);

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Home',
      'slug' => 'home',
      'page_type' => 'default',
      'status' => 'published',
      'settings' => ['public_shell' => 'docs'],
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
      ['site_id' => $site->id, 'name' => 'Home', 'slug' => 'home', 'path' => '/'],
    );

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $sidebarType->id,
      'sort_order' => 0,
      'settings' => ['wrapper_preset' => 'docs-sidebar', 'wrapper_element' => 'aside'],
    ]);
    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $headerType->id,
      'sort_order' => 1,
      'settings' => ['wrapper_preset' => 'docs-navbar', 'wrapper_element' => 'header'],
    ]);
    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $mainType->id,
      'sort_order' => 2,
      'settings' => ['wrapper_preset' => 'docs-main', 'wrapper_element' => 'main'],
    ]);
    $this->assertSame([null, null, null], $page->fresh()->slots()->orderBy('sort_order')->get()->pluck('settings')->values()->all());
    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => SlotType::query()->updateOrCreate(['slug' => 'footer'], ['name' => 'Footer', 'status' => 'published', 'sort_order' => 4, 'is_system' => true])->id,
      'sort_order' => 3,
    ]);

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'breadcrumb',
      'block_type_id' => $this->blockType('breadcrumb', 'Breadcrumb', 13, true)->id,
      'source_type' => 'static',
      'slot' => 'header',
      'slot_type_id' => $headerType->id,
      'sort_order' => 0,
      'settings' => json_encode(['include_current' => true], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => true,
    ]);

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'header-actions',
      'block_type_id' => $this->blockType('header-actions', 'Header Actions', 14, true)->id,
      'source_type' => 'static',
      'slot' => 'header',
      'slot_type_id' => $headerType->id,
      'sort_order' => 1,
      'settings' => json_encode(['show_mode_toggle' => true, 'show_accent_toggle' => true], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => true,
    ]);

    $mainBlock = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'plain_text',
      'block_type_id' => $this->blockType('plain_text', 'Plain Text', 2)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $mainType->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $mainBlock->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'content' => 'Docs main content',
    ]);

    $sidebarBlock = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'plain_text',
      'block_type_id' => $this->blockType('plain_text', 'Plain Text', 2)->id,
      'source_type' => 'static',
      'slot' => 'sidebar',
      'slot_type_id' => $sidebarType->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $sidebarBlock->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'content' => 'Docs sidebar content',
    ]);

    $footerBlock = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'plain_text',
      'block_type_id' => $this->blockType('plain_text', 'Plain Text', 2)->id,
      'source_type' => 'static',
      'slot' => 'footer',
      'slot_type_id' => SlotType::query()->where('slug', 'footer')->value('id'),
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $footerBlock->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'content' => 'Footer support content',
    ]);

    $response = $this->get('/');
    $html = $response->getContent();

    $response->assertOk();
    $response->assertSee('<div class="wb-dashboard-shell">', false);
    $response->assertSee('<div class="wb-sidebar-backdrop" data-wb-sidebar-backdrop></div>', false);
    $response->assertSee('<div class="wb-dashboard-body wb-w-full">', false);
    $response->assertSee('<nav data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full">', false);
    $response->assertSee('<div class="wb-flex wb-items-center wb-justify-between wb-gap-3 wb-w-full wb-flex-wrap">', false);
    $response->assertDontSee('<div class="wb-container wb-container-lg wb-flex wb-items-center wb-justify-between wb-gap-3 wb-w-full wb-flex-wrap">', false);
    $response->assertDontSee('wb-container wb-container-lg', false);
    $response->assertDontSee('wb-container-xl', false);
    $response->assertDontSee('wb-navbar-spacer', false);
    $response->assertSeeInOrder([
      '<div class="wb-sidebar-backdrop" data-wb-sidebar-backdrop></div>',
      '<aside data-wb-slot="sidebar" id="docsSidebar" class="wb-sidebar">',
      '<div class="wb-dashboard-body wb-w-full">',
      '<nav data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full">',
      '<main data-wb-slot="main" id="main-content" class="wb-dashboard-main">',
      '<footer data-wb-slot="footer">',
    ], false);
    $response->assertSee('<nav data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full">', false);
    $response->assertSeeInOrder([
      '<nav class="wb-breadcrumb" aria-label="Breadcrumb">',
      'data-wb-header-actions',
    ], false);
    $response->assertSee('<main data-wb-slot="main" id="main-content" class="wb-dashboard-main">', false);
    $response->assertSee('<aside data-wb-slot="sidebar" id="docsSidebar" class="wb-sidebar">', false);
    $response->assertDontSee('wb-docs-shell', false);
    $response->assertDontSee('wb-docs-content', false);
    $response->assertDontSee('wb-content-shell', false);
    $response->assertDontSee('wb-docs-main', false);
    $response->assertDontSee('<nav class="wb-navbar wb-navbar-glass"', false);
    $this->assertMatchesRegularExpression('/<div class="wb-dashboard-shell">\s*<aside\b[^>]*data-wb-slot="sidebar"[^>]*>.*?<\/aside>\s*<div class="wb-dashboard-body wb-w-full">\s*<nav\b[^>]*data-wb-slot="header"[^>]*>.*?<main\b[^>]*data-wb-slot="main"[^>]*>/s', $html);
    $this->assertDoesNotMatchRegularExpression('/<div class="wb-dashboard-shell">\s*<aside\b[^>]*data-wb-slot="sidebar"[^>]*>.*?<\/aside>\s*<header\b[^>]*data-wb-slot="header"[^>]*>/s', $html);
  }

  #[Test]
  public function alert_renders_title_content_and_variant_class(): void
  {
    $page = $this->pageWithMainSlot();
    $alert = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'alert',
      'block_type_id' => $this->blockType('alert', 'Alert', 9)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['variant' => 'success'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $alert->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'What this page is proving',
      'content' => 'This page proves docs callouts can ship as first-class blocks.',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($alert->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<div class="wb-alert wb-alert-success">', false);
    $response->assertSee('<h3 class="wb-alert-title">What this page is proving</h3>', false);
    $response->assertSee('<p>This page proves docs callouts can ship as first-class blocks.</p>', false);
    $response->assertDontSee('<strong>', false);
    $response->assertDontSee('<div class="wb-alert-title">', false);
  }

  #[Test]
  public function alert_skips_empty_title_and_content_markup(): void
  {
    $page = $this->pageWithMainSlot();

    $titleOnlyAlert = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'alert',
      'block_type_id' => $this->blockType('alert', 'Alert', 9)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['variant' => 'warning'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $titleOnlyAlert->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Title only alert',
      'content' => '   ',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($titleOnlyAlert->fresh(['textTranslations']));

    $contentOnlyAlert = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'alert',
      'block_type_id' => $this->blockType('alert', 'Alert', 9)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 1,
      'settings' => json_encode(['variant' => 'info'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $contentOnlyAlert->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => '   ',
      'content' => 'Content only alert.',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($contentOnlyAlert->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<div class="wb-alert wb-alert-warning">', false);
    $response->assertSee('<h3 class="wb-alert-title">Title only alert</h3>', false);
    $response->assertDontSee('<p></p>', false);
    $response->assertSee('<div class="wb-alert wb-alert-info">', false);
    $response->assertSee('<p>Content only alert.</p>', false);
    $response->assertDontSee('<h3 class="wb-alert-title"></h3>', false);
  }

  #[Test]
  public function alert_defaults_to_info_variant_and_invalid_variant_falls_back_to_info(): void
  {
    $page = $this->pageWithMainSlot();

    foreach ([null, 'ghost'] as $index => $variant) {
      $alert = Block::query()->create([
        'page_id' => $page->id,
        'type' => 'alert',
        'block_type_id' => $this->blockType('alert', 'Alert', 9)->id,
        'source_type' => 'static',
        'slot' => 'main',
        'slot_type_id' => $this->mainSlotType()->id,
        'sort_order' => $index,
        'settings' => json_encode(array_filter(['variant' => $variant], fn ($value) => $value !== null), JSON_UNESCAPED_SLASHES),
        'status' => 'published',
        'is_system' => false,
      ]);

      $alert->textTranslations()->create([
        'locale_id' => Page::defaultLocaleId(),
        'title' => 'Alert '.$index,
        'content' => 'Fallback variant should stay info.',
      ]);
      app(BlockTranslationWriter::class)->normalizeCanonicalStorage($alert->fresh(['textTranslations']));
    }

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $this->assertSame(2, substr_count($response->getContent(), 'wb-alert wb-alert-info'));
    $response->assertDontSee('wb-alert-success', false);
  }

  #[Test]
  public function grid_renders_child_cards_with_webblocks_grid_and_card_markup(): void
  {
    $page = $this->pageWithMainSlot();
    $grid = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'grid',
      'block_type_id' => $this->blockType('grid', 'Grid', 5)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['columns' => '3', 'gap' => '4'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $card = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $grid->id,
      'type' => 'card',
      'block_type_id' => $this->blockType('card', 'Card', 8)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $cardHeader = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $card->id,
      'type' => 'card_header',
      'block_type_id' => $this->blockType('card_header', 'Card Header', 8)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $cardBody = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $card->id,
      'type' => 'card_body',
      'block_type_id' => $this->blockType('card_body', 'Card Body', 8)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 1,
      'status' => 'published',
      'is_system' => false,
    ]);

    $cardFooter = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $card->id,
      'type' => 'card_footer',
      'block_type_id' => $this->blockType('card_footer', 'Card Footer', 8)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 2,
      'status' => 'published',
      'is_system' => false,
    ]);

    $subtitle = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $cardHeader->id,
      'type' => 'plain_text',
      'block_type_id' => $this->blockType('plain_text', 'Plain Text', 5)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $subtitle->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'content' => 'How to build',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($subtitle->fresh(['textTranslations']));

    $title = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $cardBody->id,
      'type' => 'html',
      'block_type_id' => $this->blockType('html', 'HTML (Trusted)', 99)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'content' => '<strong>Pattern-first workflow</strong>',
      'status' => 'published',
      'is_system' => false,
    ]);

    $description = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $cardBody->id,
      'type' => 'plain_text',
      'block_type_id' => $this->blockType('plain_text', 'Plain Text', 5)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 1,
      'status' => 'published',
      'is_system' => false,
    ]);
    $description->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'content' => 'Start from the nearest shipped pattern and trim it to fit the page job.',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($description->fresh(['textTranslations']));

    $button = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $cardFooter->id,
      'type' => 'button_link',
      'block_type_id' => $this->blockType('button_link', 'Button Link', 7)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'secondary',
      'settings' => json_encode(['url' => '/getting-started', 'target' => '_self'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $button->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Read more',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($button->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSeeInOrder([
      '<div class="wb-grid wb-grid-3 wb-gap-4" data-wb-public-block-type="grid">',
      '<article class="wb-card" data-wb-public-block-type="card">',
      '<div class="wb-card-header" data-wb-public-block-type="card-header">',
      '<p>How to build</p>',
      '<div class="wb-card-body" data-wb-public-block-type="card-body">',
      '<strong>Pattern-first workflow</strong>',
      '<p>Start from the nearest shipped pattern and trim it to fit the page job.</p>',
      '<div class="wb-card-footer" data-wb-public-block-type="card-footer">',
      '<a href="/getting-started" class="wb-btn wb-btn-secondary">Read more</a>',
    ], false);
  }

  #[Test]
  public function card_renders_nested_header_body_and_footer_regions(): void
  {
    $page = $this->pageWithMainSlot();
    $card = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'card',
      'block_type_id' => $this->blockType('card', 'Card', 8)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $cardHeader = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $card->id,
      'type' => 'card_header',
      'block_type_id' => $this->blockType('card_header', 'Card Header', 8)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $cardBody = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $card->id,
      'type' => 'card_body',
      'block_type_id' => $this->blockType('card_body', 'Card Body', 8)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 1,
      'status' => 'published',
      'is_system' => false,
    ]);

    $cardFooter = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $card->id,
      'type' => 'card_footer',
      'block_type_id' => $this->blockType('card_footer', 'Card Footer', 8)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 2,
      'status' => 'published',
      'is_system' => false,
    ]);

    $header = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $cardHeader->id,
      'type' => 'header',
      'block_type_id' => $this->blockType('header', 'Header', 5)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'h2',
      'status' => 'published',
      'is_system' => false,
    ]);
    $header->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Contact',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($header->fresh(['textTranslations']));

    $richText = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $cardBody->id,
      'type' => 'rich-text',
      'block_type_id' => $this->blockType('rich-text', 'Rich Text', 6)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $richText->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'content' => '<p>Website, address, phone, and VAT details live here.</p>',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($richText->fresh(['textTranslations']));

    $cluster = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $cardFooter->id,
      'type' => 'cluster',
      'block_type_id' => $this->blockType('cluster', 'Cluster', 4)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $button = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $cluster->id,
      'type' => 'button_link',
      'block_type_id' => $this->blockType('button_link', 'Button Link', 7)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'primary',
      'settings' => json_encode(['url' => '/contact', 'target' => '_self'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $button->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Open Contact',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($button->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSeeInOrder([
      '<article class="wb-card" data-wb-public-block-type="card">',
      '<div class="wb-card-header" data-wb-public-block-type="card-header">',
      '<h2 data-wb-public-block-type="header">Contact</h2>',
      '<div class="wb-card-body" data-wb-public-block-type="card-body">',
      '<div class="wb-rich-text wb-rich-text-readable">',
      'Website, address, phone, and VAT details live here.',
      '<div class="wb-card-footer" data-wb-public-block-type="card-footer">',
      '<div class="wb-cluster" data-wb-public-block-type="cluster">',
      '<a href="/contact" class="wb-btn wb-btn-primary">Open Contact</a>',
    ], false);
  }

  #[Test]
  public function legacy_card_without_region_children_still_renders_saved_copy_and_action(): void
  {
    $page = $this->pageWithMainSlot();
    $card = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'card',
      'block_type_id' => $this->blockType('card', 'Card', 8)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'title' => 'Pattern-first workflow',
      'subtitle' => 'How to build',
      'content' => 'Mode is `auto`. <script>alert(1)</script>',
      'meta' => 'Read more',
      'settings' => json_encode(['url' => '/getting-started', 'target' => '_blank'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<div class="wb-card-header">How to build</div>', false);
    $response->assertSee('<p class="wb-m-0">Mode is <code>auto</code>. &lt;script&gt;alert(1)&lt;/script&gt;</p>', false);
    $response->assertSee('<a href="/getting-started" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">Read more</a>', false);
    $response->assertDontSee('<script>alert(1)</script>', false);
  }

  #[Test]
  public function html_block_renders_trusted_static_icon_markup_without_public_javascript(): void
  {
    $page = $this->pageWithMainSlot();

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'html',
      'block_type_id' => $this->blockType('html', 'HTML (Trusted)', 99)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'content' => '<div class="wb-card"><div class="wb-card-body wb-stack wb-gap-2"><i class="wb-icon wb-icon-home" aria-hidden="true"></i><strong>Home</strong></div></div>',
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<i class="wb-icon wb-icon-home" aria-hidden="true"></i>', false);
    $response->assertSee('<strong>Home</strong>', false);
    $response->assertDontSee('data-wb-rich-text-editor', false);
    $response->assertDontSee('<script>', false);
  }

  #[Test]
  public function html_block_hoists_embedded_overlay_root_markup_into_the_shared_public_overlay_root(): void
  {
    $page = $this->pageWithMainSlot();

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'html',
      'block_type_id' => $this->blockType('html', 'HTML (Trusted)', 99)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'content' => '<section><button class="wb-gallery-trigger" type="button" data-wb-gallery-target="#trusted-viewer" data-wb-gallery-full="/storage/example.jpg">Open</button></section><div id="wb-overlay-root"><div class="wb-modal" id="trusted-viewer" role="dialog"></div></div>',
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('data-wb-gallery-target="#trusted-viewer"', false);
    $response->assertSee('<div id="wb-overlay-root" class="wb-overlay-root">', false);
    $this->assertSame(1, substr_count($response->getContent(), 'id="wb-overlay-root"'));
    $response->assertDontSee('id="wb-public-overlay-root"', false);
    $response->assertSee('<div class="wb-modal" id="trusted-viewer" role="dialog"></div>', false);
    $this->assertMatchesRegularExpression('/id="wb-overlay-root" class="wb-overlay-root">.*id="trusted-viewer"/s', $response->getContent());
  }

  #[Test]
  public function html_block_hoists_detached_trusted_modal_and_gallery_targets_into_the_shared_public_overlay_root(): void
  {
    $page = $this->pageWithMainSlot();

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'html',
      'block_type_id' => $this->blockType('html', 'HTML (Trusted)', 99)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'content' => '<section class="wb-stack wb-gap-3"><button class="wb-btn wb-btn-outline" type="button" data-wb-toggle="modal" data-wb-target="#trusted-modal">Open modal</button><button class="wb-gallery-trigger" type="button" data-wb-gallery-target="#trusted-gallery-viewer" data-wb-gallery-full="/storage/example.jpg" data-wb-gallery-alt="Example image">Open viewer</button><div class="wb-card"><div class="wb-modal wb-modal-sm" id="trusted-modal" role="dialog" aria-modal="true" aria-labelledby="trusted-modal-title"><div class="wb-modal-dialog"><div class="wb-modal-header"><h2 class="wb-modal-title" id="trusted-modal-title">Trusted modal</h2></div></div></div><div class="wb-modal wb-modal-xl" id="trusted-gallery-viewer" role="dialog" aria-modal="true" aria-labelledby="trusted-gallery-viewer-title"><div class="wb-modal-dialog"><div class="wb-modal-body"><figure class="wb-gallery-viewer-media"><img class="wb-gallery-viewer-image" src="/storage/example.jpg" alt="Example image"><figcaption class="wb-gallery-viewer-caption" id="trusted-gallery-viewer-title">Example viewer</figcaption></figure></div></div></div></div></section>',
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->get(route('pages.show', 'about'));
    $html = $response->getContent();

    $response->assertOk();
    $response->assertSee('<script src="'.WebBlocks::uiJsUrl().'" defer></script>', false);
    $this->assertSame(1, substr_count($html, 'id="wb-overlay-root"'));
    $this->assertSame(1, substr_count($html, 'class="wb-overlay-root"'));
    $response->assertSee('data-wb-toggle="modal"', false);
    $response->assertSee('data-wb-target="#trusted-modal"', false);
    $response->assertSee('data-wb-gallery-target="#trusted-gallery-viewer"', false);
    $this->assertMatchesRegularExpression('/id="wb-overlay-root" class="wb-overlay-root">.*id="trusted-modal"/s', $html);
    $this->assertMatchesRegularExpression('/id="wb-overlay-root" class="wb-overlay-root">.*id="trusted-gallery-viewer"/s', $html);
    $this->assertStringNotContainsString('wb-overlay-layer--dialog" hidden', $html);
  }

  #[Test]
  public function showcase_list_registers_a_gallery_viewer_target_under_the_shared_overlay_root(): void
  {
    $page = $this->pageWithMainSlot('Showcase Page', 'showcase-page');
    $imageOne = Media::query()->create([
      'disk' => 'public',
      'path' => 'media/images/showcase-one.jpg',
      'filename' => 'showcase-one.jpg',
      'original_name' => 'showcase-one.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1200,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Showcase One',
      'alt_text' => 'Showcase alt one',
      'width' => 1600,
      'height' => 900,
    ]);
    $imageTwo = Media::query()->create([
      'disk' => 'public',
      'path' => 'media/images/showcase-two.jpg',
      'filename' => 'showcase-two.jpg',
      'original_name' => 'showcase-two.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1400,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Showcase Two',
      'alt_text' => 'Showcase alt two',
      'width' => 1280,
      'height' => 720,
    ]);

    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'showcase-list',
      'block_type_id' => $this->blockType('showcase-list', 'Showcase List', 99)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode([
        'items' => [
          [
            'title' => 'Web App Project',
            'subtitle' => 'Reference app',
            'url' => 'https://example.test/project',
            'url_label' => 'Visit project',
            'images' => [
              ['asset_id' => $imageOne->id, 'title' => 'Dashboard'],
              ['asset_id' => $imageTwo->id, 'title' => 'Settings'],
            ],
          ],
        ],
      ], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->get(route('pages.show', 'showcase-page'));
    $html = $response->getContent();
    $viewerId = 'wb-gallery-viewer-'.$block->id;

    $response->assertOk();
    $this->assertSame(1, substr_count($html, 'id="wb-overlay-root"'));
    $this->assertSame(1, substr_count($html, 'class="wb-overlay-root"'));
    $this->assertSame(2, substr_count($html, 'class="wb-gallery-trigger"'));
    $this->assertSame(2, substr_count($html, 'data-wb-gallery-target="#'.$viewerId.'"'));
    $this->assertSame(2, substr_count($html, 'data-wb-gallery-full='));
    $this->assertSame(2, substr_count($html, 'data-wb-gallery-alt="Showcase alt'));
    $this->assertStringContainsString('showcase-one.jpg', $html);
    $this->assertStringContainsString('showcase-two.jpg', $html);
    $this->assertMatchesRegularExpression('/href="[^"]*showcase-one\.jpg"/', $html);
    $this->assertStringContainsString('data-wb-gallery-target="#'.$viewerId.'"', $html);
    $this->assertMatchesRegularExpression('/id="wb-overlay-root" class="wb-overlay-root">.*id="'.preg_quote($viewerId, '/').'"/s', $html);
    $this->assertMatchesRegularExpression('/<div class="wb-modal wb-modal-xl" id="'.preg_quote($viewerId, '/').'".*class="wb-gallery-viewer"/s', $html);
    $this->assertStringContainsString('data-wb-gallery-caption="Dashboard"', $html);
    $this->assertStringContainsString('data-wb-gallery-caption="Settings"', $html);
  }

  #[Test]
  public function gallery_block_keeps_registering_a_matching_shared_overlay_viewer(): void
  {
    $page = $this->pageWithMainSlot('Gallery Page', 'gallery-page');
    $image = Media::query()->create([
      'disk' => 'public',
      'path' => 'media/images/gallery-check.jpg',
      'filename' => 'gallery-check.jpg',
      'original_name' => 'gallery-check.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1024,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Gallery Check',
      'alt_text' => 'Gallery check alt',
      'caption' => 'Gallery caption',
      'description' => 'Gallery meta',
      'width' => 1200,
      'height' => 800,
    ]);

    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'gallery',
      'block_type_id' => $this->blockType('gallery', 'Gallery', 98)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode([
        'asset_ids' => [$image->id],
      ], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->get(route('pages.show', 'gallery-page'));
    $html = $response->getContent();
    $viewerId = 'wb-gallery-viewer-'.$block->id;

    $response->assertOk();
    $this->assertSame(1, substr_count($html, 'id="wb-overlay-root"'));
    $this->assertStringContainsString('data-wb-gallery-target="#'.$viewerId.'"', $html);
    $this->assertMatchesRegularExpression('/id="wb-overlay-root" class="wb-overlay-root">.*id="'.preg_quote($viewerId, '/').'"/s', $html);
  }

  #[Test]
  public function showcase_list_skips_unsafe_project_links_but_keeps_gallery_overlay_contract(): void
  {
    $page = $this->pageWithMainSlot('Unsafe Showcase Page', 'unsafe-showcase-page');
    $image = Media::query()->create([
      'disk' => 'public',
      'path' => 'media/images/showcase-unsafe.jpg',
      'filename' => 'showcase-unsafe.jpg',
      'original_name' => 'showcase-unsafe.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1200,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Showcase Unsafe',
      'alt_text' => 'Showcase unsafe alt',
      'width' => 1600,
      'height' => 900,
    ]);

    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'showcase-list',
      'block_type_id' => $this->blockType('showcase-list', 'Showcase List', 99)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode([
        'items' => [[
          'title' => 'Unsafe project',
          'url' => 'javascript:alert(1)',
          'url_label' => 'Open project',
          'images' => [
            ['asset_id' => $image->id, 'title' => 'Unsafe image'],
          ],
        ]],
      ], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->get(route('pages.show', 'unsafe-showcase-page'));
    $html = $response->getContent();
    $viewerId = 'wb-gallery-viewer-'.$block->id;

    $response->assertOk();
    $this->assertStringNotContainsString('javascript:alert(1)', $html);
    $this->assertStringNotContainsString('>Open project</a>', $html);
    $this->assertStringContainsString('data-wb-gallery-target="#'.$viewerId.'"', $html);
    $this->assertMatchesRegularExpression('/id="wb-overlay-root" class="wb-overlay-root">.*id="'.preg_quote($viewerId, '/').'"/s', $html);
  }

  #[Test]
  public function rich_text_block_supports_safe_html_rendering_without_raw_html_passthrough(): void
  {
    $page = $this->pageWithMainSlot();
    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'rich-text',
      'block_type_id' => $this->blockType('rich-text', 'Rich Text', 6)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $block->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'content' => '<p>Use <strong>bold</strong> and <code>auto</code>.</p><ul><li>First item</li><li>Second item</li></ul><script>alert(1)</script>',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('wb-rich-text', false);
    $response->assertSee('wb-rich-text-readable', false);
    $response->assertSee('<div class="wb-rich-text wb-rich-text-readable">', false);
    $response->assertSee('<p>Use <strong>bold</strong> and <code>auto</code>.</p>', false);
    $response->assertSee('<ul><li>First item</li><li>Second item</li></ul>', false);
    $response->assertDontSee('<script>alert(1)</script>', false);
    $response->assertDontSee('<div class="wb-stack wb-gap-3">', false);
    $response->assertDontSee('wb-prose', false);
  }

  #[Test]
  public function card_footer_renders_nested_cluster_actions(): void
  {
    $page = $this->pageWithMainSlot();
    $card = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'card',
      'block_type_id' => $this->blockType('card', 'Card', 8)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $cardFooter = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $card->id,
      'type' => 'card_footer',
      'block_type_id' => $this->blockType('card_footer', 'Card Footer', 8)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $cluster = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $cardFooter->id,
      'type' => 'cluster',
      'block_type_id' => $this->blockType('cluster', 'Cluster', 4)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['alignment' => 'end'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    foreach ([
      ['label' => 'Start Here', 'url' => '/start-here', 'variant' => 'primary', 'sort' => 0],
      ['label' => 'See primitives', 'url' => '/see-primitives', 'variant' => 'secondary', 'sort' => 1],
    ] as $button) {
      $child = Block::query()->create([
        'page_id' => $page->id,
        'parent_id' => $cluster->id,
        'type' => 'button_link',
        'block_type_id' => $this->blockType('button_link', 'Button Link', 7)->id,
        'source_type' => 'static',
        'slot' => 'main',
        'slot_type_id' => $this->mainSlotType()->id,
        'sort_order' => $button['sort'],
        'variant' => $button['variant'],
        'settings' => json_encode(['url' => $button['url'], 'target' => '_self'], JSON_UNESCAPED_SLASHES),
        'status' => 'published',
        'is_system' => false,
      ]);

      $child->textTranslations()->create([
        'locale_id' => Page::defaultLocaleId(),
        'title' => $button['label'],
      ]);
      app(BlockTranslationWriter::class)->normalizeCanonicalStorage($child->fresh(['textTranslations']));
    }

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSeeInOrder([
      '<article class="wb-card" data-wb-public-block-type="card">',
      '<div class="wb-card-footer" data-wb-public-block-type="card-footer">',
      '<a href="/start-here" class="wb-btn wb-btn-primary">Start Here</a>',
      '<a href="/see-primitives" class="wb-btn wb-btn-secondary">See primitives</a>',
      '</div>',
      '</article>',
    ], false);
    $response->assertSee('wb-cluster-end', false);
    $this->assertSame(1, substr_count($response->getContent(), '<div class="wb-card-footer" data-wb-public-block-type="card-footer">'));
    $this->assertStringContainsString('.wb-card-footer > .wb-cluster {', file_get_contents(public_path('cms/css/public.css')));
    $this->assertStringContainsString('width: 100%;', file_get_contents(public_path('cms/css/public.css')));
  }

  #[Test]
  public function cluster_renders_button_link_children_without_admin_name_output(): void
  {
    $page = $this->pageWithMainSlot();
    $cluster = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'cluster',
      'block_type_id' => $this->blockType('cluster', 'Cluster', 5)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['layout_name' => 'Action Row'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    foreach ([
      ['label' => 'Start here', 'url' => '/start-here', 'variant' => 'primary', 'sort' => 0],
      ['label' => 'See primitives', 'url' => '/see-primitives', 'variant' => 'secondary', 'sort' => 1],
    ] as $button) {
      $child = Block::query()->create([
        'page_id' => $page->id,
        'parent_id' => $cluster->id,
        'type' => 'button_link',
        'block_type_id' => $this->blockType('button_link', 'Button Link', 7)->id,
        'source_type' => 'static',
        'slot' => 'main',
        'slot_type_id' => $this->mainSlotType()->id,
        'sort_order' => $button['sort'],
        'variant' => $button['variant'],
        'settings' => json_encode(['url' => $button['url'], 'target' => '_self'], JSON_UNESCAPED_SLASHES),
        'status' => 'published',
        'is_system' => false,
      ]);

      $child->textTranslations()->create([
        'locale_id' => Page::defaultLocaleId(),
        'title' => $button['label'],
      ]);
      app(BlockTranslationWriter::class)->normalizeCanonicalStorage($child->fresh(['textTranslations']));
    }

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSeeInOrder([
      '<div class="wb-cluster" data-wb-public-block-type="cluster">',
      '<a href="/start-here" class="wb-btn wb-btn-primary">Start here</a>',
      '<a href="/see-primitives" class="wb-btn wb-btn-secondary">See primitives</a>',
      '</div>',
    ], false);
    $response->assertDontSee('Action Row');
  }

  #[Test]
  public function cluster_defaults_remain_backward_compatible_without_extra_classes(): void
  {
    $page = $this->pageWithMainSlot();
    $cluster = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'cluster',
      'block_type_id' => $this->blockType('cluster', 'Cluster', 5)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $child = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $cluster->id,
      'type' => 'button_link',
      'block_type_id' => $this->blockType('button_link', 'Button Link', 7)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'primary',
      'settings' => json_encode(['url' => '/start-here', 'target' => '_self'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $child->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Start here',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($child->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<div class="wb-cluster" data-wb-public-block-type="cluster">', false);
    $response->assertDontSee('wb-w-full', false);
    $response->assertDontSee('wb-flex-nowrap', false);
    $response->assertDontSee('wb-cluster-between', false);
  }

  #[Test]
  public function cluster_appends_selected_layout_classes(): void
  {
    $page = $this->pageWithMainSlot();
    $cluster = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'cluster',
      'block_type_id' => $this->blockType('cluster', 'Cluster', 5)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode([
        'width' => 'full',
        'alignment' => 'between',
        'items_alignment' => 'end',
        'wrap' => 'nowrap',
        'gap' => 'lg',
      ], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $child = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $cluster->id,
      'type' => 'button_link',
      'block_type_id' => $this->blockType('button_link', 'Button Link', 7)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'primary',
      'settings' => json_encode(['url' => '/start-here', 'target' => '_self'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $child->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Start here',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($child->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<div class="wb-cluster wb-cluster-6 wb-cluster-between wb-items-end wb-flex-nowrap wb-w-full" data-wb-public-block-type="cluster">', false);
    $response->assertDontSee('wb-cluster-3', false);
    $response->assertDontSee('wb-cms-navbar', false);
  }

  #[Test]
  public function cluster_supports_gap_none_and_stretch_bridge_classes_when_selected(): void
  {
    $page = $this->pageWithMainSlot();
    $cluster = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'cluster',
      'block_type_id' => $this->blockType('cluster', 'Cluster', 5)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode([
        'gap' => 'none',
        'items_alignment' => 'stretch',
      ], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $child = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $cluster->id,
      'type' => 'button_link',
      'block_type_id' => $this->blockType('button_link', 'Button Link', 7)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'primary',
      'settings' => json_encode(['url' => '/start-here', 'target' => '_self'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $child->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Start here',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($child->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<div class="wb-cluster wb-cms-cluster-gap-none wb-cms-items-stretch" data-wb-public-block-type="cluster">', false);
  }

  #[Test]
  public function button_link_renders_expected_anchor_markup_and_blank_target_attributes(): void
  {
    $page = $this->pageWithMainSlot();
    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'button_link',
      'block_type_id' => $this->blockType('button_link', 'Button Link', 6)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'secondary',
      'settings' => json_encode(['url' => '/primitives', 'target' => '_blank'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $block->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'See primitives',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<a href="/primitives" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">See primitives</a>', false);
    $response->assertDontSee('<div class="wb-btn', false);
  }

  #[Test]
  public function button_link_uses_shared_settings_and_translated_label_per_locale(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);

    $site = Site::query()->firstOrFail();
    $turkish = Locale::query()->updateOrCreate(
      ['code' => 'tr'],
      ['name' => 'Turkish', 'is_default' => false, 'is_enabled' => true],
    );
    $site->locales()->syncWithoutDetaching([$turkish->id]);

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'About',
      'slug' => 'about',
      'page_type' => 'default',
      'status' => 'published',
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
      ['site_id' => $site->id, 'name' => 'About', 'slug' => 'about', 'path' => '/p/about'],
    );
    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $turkish->id],
      ['site_id' => $site->id, 'name' => 'Hakkinda', 'slug' => 'hakkinda', 'path' => '/p/hakkinda'],
    );

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
    ]);

    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'button_link',
      'block_type_id' => $this->blockType('button_link', 'Button Link', 6)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'primary',
      'settings' => json_encode(['url' => '/start-here', 'target' => '_self'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $block->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Start here',
    ]);
    $block->textTranslations()->create([
      'locale_id' => $turkish->id,
      'title' => 'Buradan basla',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

    $defaultResponse = $this->get('/p/about');
    $turkishResponse = $this->get('/tr/p/hakkinda');

    $defaultResponse->assertOk();
    $defaultResponse->assertSee('<a href="/start-here" class="wb-btn wb-btn-primary">Start here</a>', false);

    $turkishResponse->assertOk();
    $turkishResponse->assertSee('<a href="/start-here" class="wb-btn wb-btn-primary">Buradan basla</a>', false);
  }

  #[Test]
  public function content_header_renders_expected_webblocks_markup_without_extra_wrappers(): void
  {
    $page = $this->pageWithMainSlot();
    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'content_header',
      'block_type_id' => $this->blockType('content_header', 'Content Header', 5)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'h4',
      'settings' => json_encode(['alignment' => 'center'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $block->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Docs title',
      'subtitle' => 'Short intro',
      'meta' => json_encode(['Updated today', '5 min read', 'API'], JSON_UNESCAPED_SLASHES),
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSeeInOrder([
      '<header class="wb-content-header wb-text-center" data-wb-public-block-type="content-header">',
      '<h1 class="wb-content-title">Docs title</h1>',
      '<p class="wb-content-subtitle">Short intro</p>',
      '<div class="wb-content-meta">',
      '<span>Updated today</span>',
      '<span class="wb-content-meta-divider"></span>',
      '<span>5 min read</span>',
      '<span class="wb-content-meta-divider"></span>',
      '<span>API</span>',
      '</div>',
      '</header>',
    ], false);
    $response->assertDontSee('<section class="wb-content-header', false);
    $response->assertDontSee('<div class="wb-content-header', false);
  }

  #[Test]
  public function content_header_skips_optional_intro_and_meta_sections_when_empty(): void
  {
    $page = $this->pageWithMainSlot();
    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'content_header',
      'block_type_id' => $this->blockType('content_header', 'Content Header', 5)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'h2',
      'status' => 'published',
      'is_system' => false,
    ]);

    $block->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Only title',
      'subtitle' => null,
      'meta' => null,
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<header class="wb-content-header" data-wb-public-block-type="content-header">', false);
    $response->assertSee('<h1 class="wb-content-title">Only title</h1>', false);
    $response->assertDontSee('wb-content-subtitle', false);
    $response->assertDontSee('wb-content-meta', false);
    $response->assertDontSee('wb-content-meta-divider', false);
  }

  #[Test]
  public function content_header_uses_shared_alignment_and_translated_fields_per_locale(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);

    $site = Site::query()->firstOrFail();
    $turkish = Locale::query()->updateOrCreate(
      ['code' => 'tr'],
      ['name' => 'Turkish', 'is_default' => false, 'is_enabled' => true],
    );
    $site->locales()->syncWithoutDetaching([$turkish->id]);

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'About',
      'slug' => 'about',
      'page_type' => 'default',
      'status' => 'published',
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
      ['site_id' => $site->id, 'name' => 'About', 'slug' => 'about', 'path' => '/p/about'],
    );
    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $turkish->id],
      ['site_id' => $site->id, 'name' => 'Hakkinda', 'slug' => 'hakkinda', 'path' => '/p/hakkinda'],
    );

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
    ]);

    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'content_header',
      'block_type_id' => $this->blockType('content_header', 'Content Header', 5)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'h3',
      'settings' => json_encode(['alignment' => 'right'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $block->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'English docs title',
      'subtitle' => 'English intro',
      'meta' => json_encode(['Updated', 'Guide'], JSON_UNESCAPED_SLASHES),
    ]);
    $block->textTranslations()->create([
      'locale_id' => $turkish->id,
      'title' => 'Turkce baslik',
      'subtitle' => 'Turkce giris',
      'meta' => json_encode(['Guncel', 'Rehber'], JSON_UNESCAPED_SLASHES),
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

    $defaultResponse = $this->get('/p/about');
    $turkishResponse = $this->get('/tr/p/hakkinda');

    $defaultResponse->assertOk();
    $defaultResponse->assertSee('<header class="wb-content-header wb-text-right" data-wb-public-block-type="content-header">', false);
    $defaultResponse->assertSee('<h1 class="wb-content-title">English docs title</h1>', false);
    $defaultResponse->assertSee('<p class="wb-content-subtitle">English intro</p>', false);
    $defaultResponse->assertSee('<span>Updated</span>', false);
    $defaultResponse->assertSee('<span>Guide</span>', false);

    $turkishResponse->assertOk();
    $turkishResponse->assertSee('<header class="wb-content-header wb-text-right" data-wb-public-block-type="content-header">', false);
    $turkishResponse->assertSee('<h1 class="wb-content-title">Turkce baslik</h1>', false);
    $turkishResponse->assertSee('<p class="wb-content-subtitle">Turkce giris</p>', false);
    $turkishResponse->assertSee('<span>Guncel</span>', false);
    $turkishResponse->assertSee('<span>Rehber</span>', false);
  }

  #[Test]
  public function content_header_ignores_legacy_saved_heading_levels_and_always_renders_h1(): void
  {
    $page = $this->pageWithMainSlot();
    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'content_header',
      'block_type_id' => $this->blockType('content_header', 'Content Header', 5)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'h6',
      'status' => 'published',
      'is_system' => false,
    ]);

    $block->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Legacy level title',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<h1 class="wb-content-title">Legacy level title</h1>', false);
    $response->assertDontSee('<h6 class="wb-content-title">Legacy level title</h6>', false);
  }

  #[Test]
  public function section_and_container_render_nested_header_and_plain_text_structure(): void
  {
    $page = $this->pageWithMainSlot();
    $section = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'section',
      'block_type_id' => $this->blockType('section', 'Section', 3)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['layout_name' => 'Hero area'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $container = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $section->id,
      'type' => 'container',
      'block_type_id' => $this->blockType('container', 'Container', 4)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['layout_name' => 'Hero content'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $header = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'header',
      'block_type_id' => $this->blockType('header', 'Header', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'h1',
      'status' => 'published',
      'is_system' => false,
    ]);
    $header->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Nested heading',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($header->fresh(['textTranslations']));

    $plainText = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'plain_text',
      'block_type_id' => $this->blockType('plain_text', 'Plain Text', 2)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 1,
      'status' => 'published',
      'is_system' => false,
    ]);
    $plainText->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'content' => 'Nested paragraph',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($plainText->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSeeInOrder([
      '<section class="wb-section wb-stack" data-wb-public-block-type="section">',
      '<div class="wb-container wb-stack" data-wb-public-block-type="container">',
      '<h1 data-wb-public-block-type="header">Nested heading</h1>',
      '<p>Nested paragraph</p>',
      '</div>',
      '</section>',
    ], false);
    $response->assertDontSee('Hero area');
    $response->assertDontSee('Hero content');
  }

  #[Test]
  public function top_level_section_wrapper_is_non_semantic_and_does_not_create_section_nesting(): void
  {
    $page = $this->pageWithMainSlot();

    $section = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'section',
      'block_type_id' => $this->blockType('section', 'Section', 3)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $container = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $section->id,
      'type' => 'container',
      'block_type_id' => $this->blockType('container', 'Container', 4)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $contentHeader = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'content_header',
      'block_type_id' => $this->blockType('content_header', 'Content Header', 7)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'h1',
      'status' => 'published',
      'is_system' => false,
    ]);
    $contentHeader->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Structured section heading',
      'subtitle' => 'Section container flow',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($contentHeader->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));
    $html = $response->getContent();

    $response->assertOk();
    $this->assertElementTag($html, '.wb-section', 'section');
    $this->assertElementTag($html, '.wb-container', 'div');
    $response->assertSeeInOrder([
      '<section class="wb-section wb-stack" data-wb-public-block-type="section">',
      '<div class="wb-container wb-stack" data-wb-public-block-type="container">',
      '<header class="wb-content-header" data-wb-public-block-type="content-header">',
    ], false);
    $response->assertDontSee('<div class="wb-public-block" data-wb-public-block-type="section">', false);
    $response->assertDontSee('<div class="wb-public-block" data-wb-public-block-type="container">', false);
    $response->assertDontSee('<div class="wb-public-block" data-wb-public-block-type="content-header">', false);
    $response->assertDontSee('<section class="wb-public-block" data-wb-public-block-type="section">', false);
    $response->assertDontSee('<section class="wb-public-block" data-wb-public-block-type="section"><section', false);
  }

  #[Test]
  public function public_rendering_only_uses_whitelisted_appearance_classes(): void
  {
    $page = $this->pageWithMainSlot();
    $section = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'section',
      'block_type_id' => $this->blockType('section', 'Section', 3)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['layout_name' => 'Feature zone', 'spacing' => 'lg', 'background' => 'muted'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $container = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $section->id,
      'type' => 'container',
      'block_type_id' => $this->blockType('container', 'Container', 4)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'settings' => json_encode(['width' => 'xl', 'alignment' => 'center', 'arbitrary' => 'wb-made-up'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $header = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'header',
      'block_type_id' => $this->blockType('header', 'Header', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'h2',
      'settings' => json_encode(['alignment' => 'center', 'class' => 'wb-content-title'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $header->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Centered heading',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($header->fresh(['textTranslations']));

    $plainText = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'plain_text',
      'block_type_id' => $this->blockType('plain_text', 'Plain Text', 2)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 1,
      'settings' => json_encode(['alignment' => 'right', 'class' => 'wb-content-subtitle'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $plainText->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'content' => 'Aligned paragraph',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($plainText->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<section class="wb-section wb-section-lg wb-stack" data-wb-public-block-type="section">', false);
    $response->assertSee('<div class="wb-container wb-container-xl wb-stack" data-wb-public-block-type="container">', false);
    $response->assertSee('<h2 class="wb-text-center" data-wb-public-block-type="header">Centered heading</h2>', false);
    $response->assertSee('<p class="wb-text-right">Aligned paragraph</p>', false);
    $response->assertDontSee('wb-bg-muted', false);
    $response->assertDontSee('wb-content-title', false);
    $response->assertDontSee('wb-content-subtitle', false);
    $response->assertDontSee('wb-made-up', false);
    $response->assertDontSee('wb-grid wb-stack', false);
  }

  #[Test]
  public function header_block_renders_selected_heading_level_with_escaped_translated_text(): void
  {
    $page = $this->pageWithMainSlot();
    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'header',
      'block_type_id' => $this->blockType('header', 'Header', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'h3',
      'status' => 'published',
      'is_system' => false,
    ]);

    $block->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Title <script>alert(1)</script>',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<h3 data-wb-public-block-type="header">Title &lt;script&gt;alert(1)&lt;/script&gt;</h3>', false);
    $response->assertDontSee('<script>alert(1)</script>', false);
  }

  #[Test]
  public function header_block_renders_canonical_anchor_id_and_ignores_invalid_saved_anchor_values(): void
  {
    $page = $this->pageWithMainSlot();

    $validAnchor = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'header',
      'block_type_id' => $this->blockType('header', 'Header', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'h2',
      'url' => '#Overview',
      'status' => 'published',
      'is_system' => false,
    ]);
    $validAnchor->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Overview',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($validAnchor->fresh(['textTranslations']));

    $invalidAnchor = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'header',
      'block_type_id' => $this->blockType('header', 'Header', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 1,
      'variant' => 'h3',
      'url' => 'bad anchor',
      'status' => 'published',
      'is_system' => false,
    ]);
    $invalidAnchor->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Bad Anchor',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($invalidAnchor->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<h2 id="Overview" data-wb-public-block-type="header">Overview</h2>', false);
    $response->assertSee('<h3 data-wb-public-block-type="header">Bad Anchor</h3>', false);
    $response->assertDontSee('id="bad anchor"', false);
  }

  #[Test]
  public function header_block_allows_digit_led_canonical_anchor_ids(): void
  {
    $page = $this->pageWithMainSlot();

    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'header',
      'block_type_id' => $this->blockType('header', 'Header', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'h2',
      'url' => '12-column-row-system',
      'status' => 'published',
      'is_system' => false,
    ]);
    $block->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => '12-column row system',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<h2 id="12-column-row-system" data-wb-public-block-type="header">12-column row system</h2>', false);
  }

  #[Test]
  public function multilingual_text_rendering_is_unchanged_when_shared_settings_are_present(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);

    $site = Site::query()->firstOrFail();
    $french = Locale::query()->updateOrCreate(
      ['code' => 'fr'],
      ['name' => 'French', 'is_default' => false, 'is_enabled' => true],
    );
    $site->locales()->syncWithoutDetaching([$french->id]);

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'About',
      'slug' => 'about',
      'page_type' => 'default',
      'status' => 'published',
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
      ['site_id' => $site->id, 'name' => 'About', 'slug' => 'about', 'path' => '/p/about'],
    );
    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $french->id],
      ['site_id' => $site->id, 'name' => 'A propos', 'slug' => 'a-propos', 'path' => '/p/a-propos'],
    );

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
    ]);

    $header = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'header',
      'block_type_id' => $this->blockType('header', 'Header', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'h2',
      'settings' => json_encode(['alignment' => 'center'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $header->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'English title',
    ]);
    $header->textTranslations()->create([
      'locale_id' => $french->id,
      'title' => 'Titre francais',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($header->fresh(['textTranslations']));

    $defaultResponse = $this->get(route('pages.show', 'about'));
    $frenchResponse = $this->get('/fr/p/a-propos');

    $defaultResponse->assertOk();
    $defaultResponse->assertSee('<h2 class="wb-text-center" data-wb-public-block-type="header">English title</h2>', false);
    $frenchResponse->assertOk();
    $frenchResponse->assertSee('<h2 class="wb-text-center" data-wb-public-block-type="header">Titre francais</h2>', false);
  }

  #[Test]
  public function plain_text_block_renders_plain_paragraph_with_escaped_translated_text(): void
  {
    $page = $this->pageWithMainSlot();
    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'plain_text',
      'block_type_id' => $this->blockType('plain_text', 'Plain Text', 2)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $block->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'content' => 'Paragraph <strong>copy</strong>',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<p>Paragraph &lt;strong&gt;copy&lt;/strong&gt;</p>', false);
    $response->assertDontSee('<strong>copy</strong>', false);
  }

  #[Test]
  public function link_list_item_omits_blank_description_wrapper_and_keeps_described_items(): void
  {
    $page = $this->pageWithMainSlot();
    $linkListItemType = $this->blockType('link-list-item', 'Link List Item', 21);
    $linkList = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'link-list',
      'block_type_id' => $this->blockType('link-list', 'Link List', 20, false, true)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $blankDescriptionItem = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $linkList->id,
      'type' => 'link-list-item',
      'block_type_id' => $linkListItemType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'url' => 'blank-description.html',
      'status' => 'published',
      'is_system' => false,
    ]);
    $blankDescriptionItem->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Blank description',
      'subtitle' => 'Optional meta',
      'content' => null,
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($blankDescriptionItem->fresh(['textTranslations']));

    $describedItem = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $linkList->id,
      'type' => 'link-list-item',
      'block_type_id' => $linkListItemType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 1,
      'url' => 'described.html',
      'status' => 'published',
      'is_system' => false,
    ]);
    $describedItem->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Described item',
      'subtitle' => null,
      'content' => 'Useful supporting text.',
    ]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($describedItem->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));
    $html = $response->getContent();

    $response->assertOk();
    $response->assertSee('href="blank-description.html"', false);
    $response->assertSee('<span class="wb-link-list-title">Blank description</span>', false);
    $response->assertSee('<span class="wb-link-list-meta">Optional meta</span>', false);
    $response->assertSee('href="described.html"', false);
    $response->assertSee('<span class="wb-link-list-title">Described item</span>', false);
    $response->assertSee('<div class="wb-link-list-desc">Useful supporting text.</div>', false);

    $this->assertNotFalse($html);
    $blankItemStart = strpos($html, 'href="blank-description.html"');
    $describedItemStart = strpos($html, 'href="described.html"');
    $this->assertNotFalse($blankItemStart);
    $this->assertNotFalse($describedItemStart);

    $blankItemMarkup = substr($html, $blankItemStart, $describedItemStart - $blankItemStart);
    $this->assertStringNotContainsString('wb-link-list-desc', $blankItemMarkup);
  }

  private function pageWithMainSlot(string $title = 'About', string $slug = 'about'): Page
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $site = Site::query()->firstOrFail();

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => $title,
      'slug' => $slug,
      'page_type' => 'default',
      'status' => 'published',
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
      ['site_id' => $site->id, 'name' => $title, 'slug' => $slug, 'path' => '/p/'.$slug],
    );

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
    ]);

    return $page;
  }

  private function mainSlotType(): SlotType
  {
    return SlotType::query()->updateOrCreate(
      ['slug' => 'main'],
      ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
    );
  }

  private function blockType(string $slug, string $name, int $sortOrder, bool $isSystem = false, bool $isContainer = false): BlockType
  {
    return BlockType::query()->updateOrCreate(
      ['slug' => $slug],
      ['name' => $name, 'source_type' => 'static', 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => $isSystem, 'is_container' => $isContainer || $slug === 'card' || in_array($slug, ['section', 'container', 'cluster', 'grid'], true)],
    );
  }
}
