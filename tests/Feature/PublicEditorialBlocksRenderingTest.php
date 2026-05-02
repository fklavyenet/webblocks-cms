<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use App\Support\Blocks\BlockTranslationWriter;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicEditorialBlocksRenderingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function canonical_public_block_renderers_exist_for_current_layout_and_content_blocks(): void
    {
        foreach (['header', 'plain_text', 'section', 'container', 'cluster', 'grid', 'content_header', 'button_link', 'card', 'alert', 'breadcrumb', 'header-actions', 'sidebar-brand', 'sidebar-navigation', 'sidebar-nav-item', 'sidebar-nav-group', 'sidebar-footer'] as $slug) {
            $this->assertTrue(View::exists('pages.partials.blocks.'.$slug));
        }
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
        $response->assertSee('Guides', false);
        $response->assertSee('href="/p/about" class="wb-nav-group-item is-active" aria-current="page"', false);
        $response->assertSee('href="https://example.com/docs"', false);
        $response->assertSee('class="wb-nav-group-item"', false);
        $response->assertSee('target="_blank" rel="noopener noreferrer"', false);
        $response->assertSee('href="/p/about" class="wb-sidebar-link is-active" aria-current="page"', false);
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
        $asset = \App\Models\Asset::query()->create([
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
        $response->assertSee('<a href="https://example.com" class="wb-sidebar-brand" target="_blank" rel="noopener noreferrer">', false);
        $response->assertSee('class="wb-sidebar-brand-logo"', false);
        $response->assertSee('webblocks-ui-logo.png', false);
        $response->assertSee('alt=""', false);
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
        $response->assertSee('<a href="/" class="wb-sidebar-brand">', false);
        $response->assertDontSee('wb-sidebar-brand-logo', false);
        $response->assertSee('<span class="wb-sidebar-brand-copy">', false);
        $response->assertSee('<span>Docs Home</span>', false);
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
        $response->assertSee('data-wb-toggle="dropdown"', false);
        $response->assertSee('data-wb-accent-set="ocean"', false);
        $response->assertSee('type="button"', false);
        $response->assertSee('aria-pressed="false"', false);
        $response->assertSee('<i class="wb-icon wb-icon-sun-moon" aria-hidden="true"></i>', false);
        $response->assertSee('<i class="wb-icon wb-icon-palette" aria-hidden="true"></i>', false);
        $response->assertSee('aria-label="Auto mode"', false);
        $response->assertSee('aria-label="Change accent color"', false);
        $response->assertSee('aria-expanded="false"', false);
        $response->assertSee('aria-haspopup="menu"', false);
        $response->assertSee('aria-controls="wb-header-actions-accent-menu-', false);
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

        Block::query()->where('page_id', $page->id)->where('type', 'header-actions')->delete();

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'header-actions',
            'block_type_id' => $this->blockType('header-actions', 'Header Actions', 14, true)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'settings' => json_encode(['show_mode_toggle' => false, 'show_accent_toggle' => false], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => true,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertDontSee('data-wb-header-actions', false);
        $response->assertDontSee('data-wb-header-actions-mode-toggle', false);
        $response->assertDontSee('data-wb-header-actions-accent-toggle', false);
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
        $turkish = \App\Models\Locale::query()->updateOrCreate(
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
        $turkishHomePath = app(\App\Support\Pages\PageRouteResolver::class)->homePath('tr', $site);

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

        $response->assertOk();
        $response->assertSee('<div class="wb-dashboard-shell">', false);
        $response->assertSee('<div class="wb-sidebar-backdrop" data-wb-sidebar-backdrop></div>', false);
        $response->assertSee('<header data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full">', false);
        $response->assertSee('<div class="wb-flex wb-items-center wb-justify-between wb-gap-3 wb-w-full wb-flex-wrap">', false);
        $response->assertDontSee('<div class="wb-container wb-container-lg wb-flex wb-items-center wb-justify-between wb-gap-3 wb-w-full wb-flex-wrap">', false);
        $response->assertDontSee('wb-container wb-container-lg', false);
        $response->assertDontSee('wb-container-xl', false);
        $response->assertDontSee('wb-navbar-spacer', false);
        $response->assertSeeInOrder([
            '<div class="wb-sidebar-backdrop" data-wb-sidebar-backdrop></div>',
            '<aside data-wb-slot="sidebar" id="docsSidebar" class="wb-sidebar">',
            '<header data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full">',
            '<main data-wb-slot="main" id="main-content" class="wb-dashboard-main">',
            '<footer data-wb-slot="footer">',
        ], false);
        $response->assertSee('<header data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full">', false);
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
            'settings' => json_encode(['url' => '/getting-started', 'target' => '_self'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $card->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Pattern-first workflow',
            'subtitle' => 'How to build',
            'content' => 'Start from the nearest shipped pattern and trim it to fit the page job.',
            'meta' => 'Read more',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($card->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSeeInOrder([
            '<div class="wb-grid wb-grid-3 wb-gap-4">',
            '<article class="wb-card">',
            '<div class="wb-card-header">How to build</div>',
            '<div class="wb-card-body wb-stack wb-gap-2">',
            '<strong>Pattern-first workflow</strong>',
            '<p class="wb-m-0">Start from the nearest shipped pattern and trim it to fit the page job.</p>',
            '<div class="wb-card-footer">',
            '<a href="/getting-started" class="wb-btn wb-btn-secondary">Read more</a>',
        ], false);
    }

    #[Test]
    public function card_renders_translation_backed_title_and_description(): void
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

        $card->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'HTML stays HTML',
            'content' => 'You write explicit markup and attach shipped WebBlocks classes.',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($card->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<article class="wb-card">', false);
        $response->assertSee('<div class="wb-card-body wb-stack wb-gap-2">', false);
        $response->assertSee('<strong>HTML stays HTML</strong>', false);
        $response->assertSee('<p class="wb-m-0">You write explicit markup and attach shipped WebBlocks classes.</p>', false);
    }

    #[Test]
    public function promo_card_renders_webblocks_promo_markup_with_optional_eyebrow(): void
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
            'settings' => json_encode(['variant' => 'promo'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $card->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'eyebrow' => 'Source-visible UI system',
            'title' => 'WebBlocks UI - UI building blocks for humans and AI.',
            'subtitle' => 'Docs entry card',
            'content' => 'Use promo cards when the docs entry point should look like a shipped marketing pattern.',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($card->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<section class="wb-card wb-promo">', false);
        $response->assertSee('<div class="wb-card-body wb-promo-copy wb-stack wb-gap-3">', false);
        $response->assertSee('<p class="wb-eyebrow">Source-visible UI system</p>', false);
        $response->assertSee('<h2 class="wb-promo-title">WebBlocks UI - UI building blocks for humans and AI.</h2>', false);
        $response->assertSee('<p class="wb-promo-text">Use promo cards when the docs entry point should look like a shipped marketing pattern.</p>', false);
        $response->assertDontSee('<article class="wb-card">', false);
    }

    #[Test]
    public function promo_card_renders_child_cluster_inside_promo_actions(): void
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
            'settings' => json_encode(['variant' => 'promo', 'url' => '/legacy-action', 'target' => '_self'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $card->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'eyebrow' => 'Source-visible UI system',
            'title' => 'WebBlocks UI - UI building blocks for humans and AI.',
            'content' => 'Card promo actions should stay nested.',
            'meta' => 'Legacy action',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($card->fresh(['textTranslations']));

        $cluster = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $card->id,
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
        $response->assertSee('<div class="wb-promo-actions wb-cluster wb-cluster-2">', false);
        $response->assertSee('wb-cluster-end', false);
        $response->assertSee('<a href="/start-here" class="wb-btn wb-btn-primary">Start Here</a>', false);
        $response->assertSee('<a href="/see-primitives" class="wb-btn wb-btn-secondary">See primitives</a>', false);
        $response->assertDontSee('<a href="/legacy-action" class="wb-btn wb-btn-secondary">Legacy action</a>', false);
    }

    #[Test]
    public function promo_card_uses_legacy_action_inside_promo_actions_when_no_children_exist(): void
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
            'settings' => json_encode(['variant' => 'promo', 'url' => '/getting-started', 'target' => '_blank'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $card->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'eyebrow' => 'Source-visible UI system',
            'title' => 'Pattern-first workflow',
            'content' => 'Use the nearest shipped pattern first.',
            'meta' => 'Read more',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($card->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<div class="wb-promo-actions wb-cluster wb-cluster-2">', false);
        $response->assertSee('<a href="/getting-started" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">Read more</a>', false);
        $response->assertDontSee('<div class="wb-card-footer">', false);
    }

    #[Test]
    public function invalid_or_missing_card_variant_falls_back_to_default_card_rendering(): void
    {
        $page = $this->pageWithMainSlot();

        foreach ([null, 'ghost'] as $index => $variant) {
            $card = Block::query()->create([
                'page_id' => $page->id,
                'type' => 'card',
                'block_type_id' => $this->blockType('card', 'Card', 8)->id,
                'source_type' => 'static',
                'slot' => 'main',
                'slot_type_id' => $this->mainSlotType()->id,
                'sort_order' => $index,
                'settings' => json_encode(array_filter(['variant' => $variant], fn ($value) => $value !== null), JSON_UNESCAPED_SLASHES),
                'status' => 'published',
                'is_system' => false,
            ]);

            $card->textTranslations()->create([
                'locale_id' => Page::defaultLocaleId(),
                'title' => 'Default card '.$index,
                'content' => 'Fallback rendering should stay on wb-card.',
            ]);
            app(BlockTranslationWriter::class)->normalizeCanonicalStorage($card->fresh(['textTranslations']));
        }

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), '<article class="wb-card">'));
        $response->assertDontSee('<section class="wb-card wb-promo">', false);
    }

    #[Test]
    public function card_renders_child_cluster_inside_footer(): void
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
            'settings' => json_encode(['url' => '/legacy-action', 'target' => '_self'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $card->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'WebBlocks UI - UI building blocks for humans and AI.',
            'content' => 'Card footer actions should be nested content.',
            'meta' => 'Legacy action',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($card->fresh(['textTranslations']));

        $cluster = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $card->id,
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
            '<article class="wb-card">',
            '<div class="wb-card-body wb-stack wb-gap-2">',
            '<strong>WebBlocks UI - UI building blocks for humans and AI.</strong>',
            '<div class="wb-card-footer">',
            '<a href="/start-here" class="wb-btn wb-btn-primary">Start Here</a>',
            '<a href="/see-primitives" class="wb-btn wb-btn-secondary">See primitives</a>',
            '</div>',
            '</article>',
        ], false);
        $response->assertSee('wb-cluster-end', false);
        $response->assertDontSee('<a href="/legacy-action" class="wb-btn wb-btn-secondary">Legacy action</a>', false);
        $this->assertSame(1, substr_count($response->getContent(), '<div class="wb-card-footer">'));
        $this->assertStringContainsString('.wb-card-footer > .wb-cluster {', file_get_contents(public_path('assets/webblocks-cms/css/public.css')));
        $this->assertStringContainsString('width: 100%;', file_get_contents(public_path('assets/webblocks-cms/css/public.css')));
    }

    #[Test]
    public function card_uses_legacy_action_footer_when_no_child_blocks_exist(): void
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
            'settings' => json_encode(['url' => '/getting-started', 'target' => '_blank'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $card->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Pattern-first workflow',
            'content' => 'Use the nearest shipped pattern first.',
            'meta' => 'Read more',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($card->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<div class="wb-card-footer">', false);
        $response->assertSee('<a href="/getting-started" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">Read more</a>', false);
        $response->assertDontSee('<div class="wb-cluster">', false);
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
            '<div class="wb-cluster">',
            '<a href="/start-here" class="wb-btn wb-btn-primary">Start here</a>',
            '<a href="/see-primitives" class="wb-btn wb-btn-secondary">See primitives</a>',
            '</div>',
        ], false);
        $response->assertDontSee('Action Row');
    }

    #[Test]
    public function cluster_appends_only_verified_gap_and_alignment_classes(): void
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
            'settings' => json_encode(['gap' => '4', 'alignment' => 'center'], JSON_UNESCAPED_SLASHES),
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
        $response->assertSee('<div class="wb-cluster wb-cluster-4 wb-cluster-center">', false);
        $response->assertDontSee('wb-cluster-3', false);
        $response->assertDontSee('wb-cluster-between', false);
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
        app(\App\Support\Blocks\BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

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
        $turkish = \App\Models\Locale::query()->updateOrCreate(
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
        app(\App\Support\Blocks\BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

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
            'variant' => 'h1',
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
            '<header class="wb-content-header wb-text-center">',
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
        $response->assertSee('<header class="wb-content-header">', false);
        $response->assertSee('<h2 class="wb-content-title">Only title</h2>', false);
        $response->assertDontSee('wb-content-subtitle', false);
        $response->assertDontSee('wb-content-meta', false);
        $response->assertDontSee('wb-content-meta-divider', false);
    }

    #[Test]
    public function content_header_uses_shared_alignment_and_translated_fields_per_locale(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $turkish = \App\Models\Locale::query()->updateOrCreate(
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
        $defaultResponse->assertSee('<header class="wb-content-header wb-text-right">', false);
        $defaultResponse->assertSee('<h3 class="wb-content-title">English docs title</h3>', false);
        $defaultResponse->assertSee('<p class="wb-content-subtitle">English intro</p>', false);
        $defaultResponse->assertSee('<span>Updated</span>', false);
        $defaultResponse->assertSee('<span>Guide</span>', false);

        $turkishResponse->assertOk();
        $turkishResponse->assertSee('<header class="wb-content-header wb-text-right">', false);
        $turkishResponse->assertSee('<h3 class="wb-content-title">Turkce baslik</h3>', false);
        $turkishResponse->assertSee('<p class="wb-content-subtitle">Turkce giris</p>', false);
        $turkishResponse->assertSee('<span>Guncel</span>', false);
        $turkishResponse->assertSee('<span>Rehber</span>', false);
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
            '<section class="wb-section wb-stack">',
            '<div class="wb-container wb-stack">',
            '<h1>Nested heading</h1>',
            '<p>Nested paragraph</p>',
            '</div>',
            '</section>',
        ], false);
        $response->assertDontSee('Hero area');
        $response->assertDontSee('Hero content');
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
        $response->assertSee('<section class="wb-section wb-section-lg wb-stack">', false);
        $response->assertSee('<div class="wb-container wb-container-xl wb-stack">', false);
        $response->assertSee('<h2 class="wb-text-center">Centered heading</h2>', false);
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
        $response->assertSee('<h3>Title &lt;script&gt;alert(1)&lt;/script&gt;</h3>', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    #[Test]
    public function multilingual_text_rendering_is_unchanged_when_shared_settings_are_present(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $french = \App\Models\Locale::query()->updateOrCreate(
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
        $defaultResponse->assertSee('<h2 class="wb-text-center">English title</h2>', false);
        $frenchResponse->assertOk();
        $frenchResponse->assertSee('<h2 class="wb-text-center">Titre francais</h2>', false);
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

    private function blockType(string $slug, string $name, int $sortOrder, bool $isSystem = false): BlockType
    {
        return BlockType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'source_type' => 'static', 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => $isSystem, 'is_container' => $slug === 'card' || in_array($slug, ['section', 'container', 'cluster', 'grid'], true)],
        );
    }
}
