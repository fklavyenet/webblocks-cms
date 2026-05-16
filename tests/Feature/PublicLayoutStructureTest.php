<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageAsset;
use App\Models\PageLayout;
use App\Models\PageLayoutSlot;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use App\Support\Blocks\BlockTranslationWriter;
use App\Support\WebBlocks;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Database\Seeders\PageLayoutSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicLayoutStructureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function public_layout_renders_ordered_slot_wrappers(): void
    {
        $this->buildHomepageWithHeaderSidebarAndFooter();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSeeInOrder([
            '<header data-wb-slot="header" class="wb-public-site-header">',
            '<main data-wb-slot="main" id="main-content">',
            '<aside data-wb-slot="sidebar">',
            '<footer data-wb-slot="footer">',
        ], false);
    }

    #[Test]
    public function public_body_includes_seeded_layout_body_class(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(PageLayoutSeeder::class);

        $page = $this->buildHomepageWithHeaderSidebarAndFooter();
        $page->update(['settings' => ['public_shell' => 'docs']]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<body class="wb-public-body layout-docs">', false);
    }

    #[Test]
    public function custom_layout_body_class_and_slot_classes_render_safely(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->slotType('header', 'Header', 1);
        $main = $this->slotType('main', 'Main', 2);

        $layout = PageLayout::query()->create([
            'name' => 'Marketing Layout',
            'handle' => 'marketing',
            'description' => 'Marketing',
            'is_active' => true,
            'sort_order' => 20,
            'body_class' => 'layout-marketing hero-shell',
            'shell_type' => 'default',
        ]);

        PageLayoutSlot::query()->create([
            'page_layout_id' => $layout->id,
            'slot_type_id' => $main->id,
            'slot_name' => 'main',
            'label' => 'Main',
            'html_element' => 'section',
            'html_classes' => 'marketing-main wb-sticky',
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $site = Site::query()->firstOrFail();
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Marketing',
            'slug' => 'marketing',
            'status' => 'published',
            'settings' => ['public_shell' => 'marketing'],
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $site->id, 'name' => 'Marketing', 'slug' => 'marketing', 'path' => '/p/marketing'],
        );

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $response = $this->get('/p/marketing');

        $response->assertOk();
        $response->assertSee('<body class="wb-public-body layout-marketing hero-shell">', false);
        $response->assertSee('<section data-wb-slot="main" class="marketing-main wb-sticky">', false);
    }

    #[Test]
    public function public_layout_loads_cms_public_stylesheet_in_head(): void
    {
        $this->buildHomepageWithHeaderSidebarAndFooter();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('cms/css/public.css', false);
    }

    #[Test]
    public function resolved_site_level_public_assets_render_from_the_current_site_when_files_exist(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $defaultSite = Site::query()->firstOrFail();
        $defaultSite->update(['domain' => 'default.example.test']);

        $this->putTrackedPublicSiteFile('site/default/css/site.css', 'body { color: red; }');
        $this->putTrackedPublicSiteFile('site/default/js/site.js', 'window.defaultSiteLoaded = true;');

        $secondarySite = Site::query()->create([
            'name' => 'Docs Site',
            'handle' => 'docs-site',
            'domain' => 'docs.example.test',
            'is_primary' => false,
        ]);

        $header = $this->slotType('header', 'Header', 1);
        $main = $this->slotType('main', 'Main', 2);
        $footer = $this->slotType('footer', 'Footer', 3);
        $headerType = $this->blockType('header', 'Header', 1);

        $page = Page::query()->create([
            'site_id' => $secondarySite->id,
            'title' => 'Docs Home',
            'slug' => 'docs-home',
            'status' => 'published',
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $secondarySite->id, 'name' => 'Docs Home', 'slug' => 'docs-home', 'path' => '/'],
        );

        foreach ([[$header, 0], [$main, 1], [$footer, 2]] as [$slotType, $sortOrder]) {
            PageSlot::query()->create([
                'page_id' => $page->id,
                'slot_type_id' => $slotType->id,
                'sort_order' => $sortOrder,
            ]);
        }

        $headerBlock = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'header',
            'block_type_id' => $headerType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $header->id,
            'sort_order' => 0,
            'variant' => 'h1',
            'status' => 'published',
            'is_system' => false,
        ]);
        $headerBlock->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Docs Home',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($headerBlock->fresh(['textTranslations']));

        $this->putTrackedPublicSiteFile('site/docs-site/css/site.css', 'body { color: blue; }');
        $this->putTrackedPublicSiteFile('site/docs-site/js/site.js', 'window.docsSiteLoaded = true;');

        $response = $this->get('http://docs.example.test/');
        $headHtml = $this->headHtml($response->getContent());

        $response->assertOk();
        $response->assertSee('cms/css/public.css', false);
        $response->assertSee('/site/docs-site/css/site.css', false);
        $response->assertSee('/site/docs-site/js/site.js', false);
        $response->assertDontSee('/site/default/css/site.css', false);
        $response->assertDontSee('/site/default/js/site.js', false);
        $this->assertStringContainsString('<link rel="stylesheet" href="/site/docs-site/css/site.css">', $headHtml);
        $this->assertStringContainsString('<script src="/site/docs-site/js/site.js" defer></script>', $headHtml);
    }

    #[Test]
    public function page_assets_render_only_on_the_owning_public_page(): void
    {
        $page = $this->buildHomepageWithHeaderSidebarAndFooter();

        PageAsset::query()->create([
            'page_id' => $page->id,
            'type' => 'css',
            'path' => '/site/default/pages/home/page.css',
            'load_position' => 'head',
            'is_enabled' => true,
            'sort_order' => 0,
        ]);
        PageAsset::query()->create([
            'page_id' => $page->id,
            'type' => 'js',
            'path' => '/site/default/pages/home/page.js',
            'load_position' => 'body_end',
            'is_defer' => true,
            'is_enabled' => true,
            'sort_order' => 1,
        ]);
        PageAsset::query()->create([
            'page_id' => $page->id,
            'type' => 'js',
            'path' => '/site/default/pages/home/disabled.js',
            'load_position' => 'body_end',
            'is_enabled' => false,
            'sort_order' => 2,
        ]);

        $response = $this->get('/');
        $headHtml = $this->headHtml($response->getContent());
        $bodyHtml = $this->bodyHtml($response->getContent());

        $response->assertOk();
        $response->assertSee('/site/default/pages/home/page.css', false);
        $response->assertSee('/site/default/pages/home/page.js', false);
        $response->assertDontSee('/site/default/pages/home/disabled.js', false);
        $this->assertStringContainsString('/site/default/pages/home/page.css', $headHtml);
        $this->assertStringContainsString('/site/default/pages/home/page.js', $headHtml);
        $this->assertStringNotContainsString('/site/default/pages/home/page.js', $bodyHtml);

        $otherPage = Page::query()->create([
            'site_id' => $page->site_id,
            'title' => 'Other',
            'slug' => 'other',
            'status' => 'published',
        ]);
        PageTranslation::query()->updateOrCreate(
            ['page_id' => $otherPage->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $page->site_id, 'name' => 'Other', 'slug' => 'other', 'path' => '/p/other'],
        );

        $this->get('/p/other')
            ->assertOk()
            ->assertDontSee('/site/default/pages/home/page.css', false)
            ->assertDontSee('/site/default/pages/home/page.js', false);
    }

    #[Test]
    public function public_layout_uses_pinned_webblocks_ui_v275_assets_and_not_master_urls(): void
    {
        $this->buildHomepageWithHeaderSidebarAndFooter();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(WebBlocks::uiCssUrl(), false);
        $response->assertSee(WebBlocks::iconsCssUrl(), false);
        $response->assertSee(WebBlocks::uiJsUrl(), false);
        $response->assertSee('<script src="'.WebBlocks::uiJsUrl().'" defer></script>', false);
        $response->assertSee('webblocks-ui@v2.7.5', false);
        $response->assertDontSee('cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master', false);
    }

    #[Test]
    public function public_named_javascript_assets_render_in_head_with_defer_in_dependency_safe_order(): void
    {
        $page = $this->buildHomepageWithHeaderSidebarAndFooter();

        PageAsset::query()->create([
            'page_id' => $page->id,
            'type' => 'js',
            'path' => '/site/default/pages/home/page-head.js',
            'load_position' => 'head',
            'is_enabled' => true,
            'is_defer' => true,
            'sort_order' => 0,
        ]);
        PageAsset::query()->create([
            'page_id' => $page->id,
            'type' => 'js',
            'path' => '/site/default/pages/home/page-body-end.js',
            'load_position' => 'body_end',
            'is_enabled' => true,
            'is_defer' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get('/');
        $html = $response->getContent();
        $headHtml = $this->headHtml($html);
        $bodyHtml = $this->bodyHtml($html);

        $response->assertOk();
        $this->assertStringContainsString('<script src="'.WebBlocks::uiJsUrl().'" defer></script>', $headHtml);
        $this->assertMatchesRegularExpression('/cms\/js\/public\/public-search-modal\.js\?v=\d+" defer><\/script>/', $headHtml);
        $this->assertMatchesRegularExpression('/cms\/js\/public\/sidebar-navigation\.js\?v=\d+" defer><\/script>/', $headHtml);
        $this->assertMatchesRegularExpression('/<script src="\/site\/default\/pages\/home\/page-head\.js"[^>]*defer\s*><\/script>/', $headHtml);
        $this->assertMatchesRegularExpression('/<script src="\/site\/default\/pages\/home\/page-body-end\.js"[^>]*defer\s*><\/script>/', $headHtml);
        $this->assertStringNotContainsString(WebBlocks::uiJsUrl(), $bodyHtml);
        $this->assertStringNotContainsString('cms/js/public/public-search-modal.js', $bodyHtml);
        $this->assertStringNotContainsString('cms/js/public/sidebar-navigation.js', $bodyHtml);
        $this->assertStringNotContainsString('/site/default/pages/home/page-head.js', $bodyHtml);
        $this->assertStringNotContainsString('/site/default/pages/home/page-body-end.js', $bodyHtml);
        $this->assertStringContainsInOrder($headHtml, [
            WebBlocks::uiJsUrl(),
            'cms/js/public/public-search-modal.js',
            'cms/js/public/sidebar-navigation.js',
            '/site/default/pages/home/page-head.js',
            '/site/default/pages/home/page-body-end.js',
        ]);
    }

    #[Test]
    public function public_layout_renders_exactly_one_canonical_overlay_root(): void
    {
        $this->buildHomepageWithHeaderSidebarAndFooter();

        $response = $this->get('/');
        $html = $response->getContent();

        $response->assertOk();
        $this->assertSame(1, substr_count($html, 'class="wb-overlay-root"'));
        $this->assertSame(1, substr_count($html, 'id="wb-overlay-root"'));
        $response->assertDontSee('id="wb-public-overlay-root"', false);
        $response->assertDontSee('id="public-overlay-root"', false);
        $response->assertDontSee('id="overlay-root"', false);
    }

    #[Test]
    public function public_search_modal_does_not_hide_the_shared_dialog_layer(): void
    {
        $this->buildHomepageWithHeaderSidebarAndFooter();

        $response = $this->get('/');
        $html = $response->getContent();

        $response->assertOk();
        $response->assertSee('<div class="wb-overlay-layer wb-overlay-layer--dialog" data-wb-public-search-overlay>', false);
        $response->assertSee('<div class="wb-overlay-backdrop" data-wb-public-search-close hidden></div>', false);
        $response->assertDontSee('<div class="wb-overlay-layer wb-overlay-layer--dialog" data-wb-public-search-overlay hidden>', false);
        $this->assertStringNotContainsString('wb-overlay-layer--dialog" hidden', $html);
    }

    #[Test]
    public function cluster_full_width_utility_keeps_card_footer_cluster_bridge_valid(): void
    {
        $this->assertStringContainsString('.wb-card-footer > .wb-cluster {', file_get_contents(public_path('cms/css/public.css')));
        $this->assertStringContainsString('.wb-cms-cluster-gap-none {', file_get_contents(public_path('cms/css/public.css')));
        $this->assertStringContainsString('.wb-cms-items-stretch {', file_get_contents(public_path('cms/css/public.css')));
    }

    #[Test]
    public function slots_render_direct_primitive_block_output_without_extra_shell_wrappers(): void
    {
        $this->buildHomepageWithHeaderSidebarAndFooter();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<h1 data-wb-public-block-type="header">WebBlocks CMS</h1>', false);
        $response->assertSee('<p>Main slot content</p>', false);
        $response->assertSee('<p>Sidebar supporting content</p>', false);
        $response->assertSee('<p>Footer supporting content</p>', false);
        $response->assertSee('<div class="wb-stack">', false);
        $response->assertSee('<header data-wb-slot="header" class="wb-public-site-header">', false);
        $response->assertDontSee('wb-public-header', false);
        $response->assertDontSee('wb-public-sidebar', false);
        $response->assertDontSee('wb-public-footer', false);
    }

    #[Test]
    public function default_header_slot_does_not_force_stack_wrapper_around_navbar_and_shell_spacing_class_is_present(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $header = $this->slotType('header', 'Header', 1);
        $main = $this->slotType('main', 'Main', 2);
        $navbarType = $this->blockType('sticky-navbar', 'Navbar', 10);
        $brandType = $this->blockType('navbar-brand', 'Navbar Brand', 11);
        $navigationType = $this->blockType('navbar-navigation', 'Navbar Navigation', 12);
        $textType = $this->blockType('plain_text', 'Plain Text', 13);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Header Slot Shell',
            'slug' => 'header-slot-shell',
            'status' => 'published',
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $site->id, 'name' => 'Header Slot Shell', 'slug' => 'header-slot-shell', 'path' => '/p/header-slot-shell'],
        );

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $header->id,
            'sort_order' => 0,
        ]);

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 1,
        ]);

        $navbar = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'sticky-navbar',
            'block_type_id' => $navbarType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $header->id,
            'sort_order' => 0,
            'settings' => json_encode(['sticky_mode' => 'sticky'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => true,
        ]);

        $brand = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $navbar->id,
            'type' => 'navbar-brand',
            'block_type_id' => $brandType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $header->id,
            'sort_order' => 0,
            'settings' => json_encode(['url' => '/'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);
        $brand->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Shell Brand',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($brand->fresh(['textTranslations']));

        $navigation = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $navbar->id,
            'type' => 'navbar-navigation',
            'block_type_id' => $navigationType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $header->id,
            'sort_order' => 1,
            'settings' => json_encode(['menu_key' => NavigationItem::MENU_PRIMARY], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);
        $navigation->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Primary navigation',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($navigation->fresh(['textTranslations']));

        NavigationItem::query()->create([
            'site_id' => $site->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'title' => 'Header Slot Shell',
            'link_type' => NavigationItem::LINK_PAGE,
            'page_id' => $page->id,
            'position' => 1,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
        ]);

        $mainBlock = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'plain_text',
            'block_type_id' => $textType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);
        $mainBlock->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'content' => 'Main shell content',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($mainBlock->fresh(['textTranslations']));

        $response = $this->get('/p/header-slot-shell');

        $response->assertOk();
        $response->assertSee('<header data-wb-slot="header" class="wb-public-site-header">', false);
        $response->assertSee('<nav class="wb-navbar" data-wb-public-block-type="sticky-navbar">', false);
        $response->assertDontSee('wb-cms-navbar--sticky', false);
        $response->assertDontSee('<header data-wb-slot="header" class="wb-public-site-header"><div class="wb-stack">', false);
        $response->assertDontSee('<div class="wb-public-block" data-wb-public-block-type="sticky-navbar">', false);
        $response->assertSee('aria-controls="wb-navbar-navigation-mobile-menu-'.$navigation->id.'"', false);
        $response->assertSee('id="wb-navbar-navigation-mobile-menu-'.$navigation->id.'"', false);
        $response->assertSee('aria-label="Toggle navigation"', false);
        $response->assertSee('class="wb-icon wb-icon-menu" aria-hidden="true"', false);
        $response->assertDontSee('<span></span><span></span><span></span>', false);
        $response->assertSee('<main data-wb-slot="main" id="main-content">', false);
        $response->assertSee('Main shell content', false);
        $this->assertStringContainsString('.wb-public-site-header + main[data-wb-slot="main"] {', file_get_contents(public_path('cms/css/public.css')));
        $this->assertStringContainsString('.wb-public-site-header {', file_get_contents(public_path('cms/css/public.css')));
    }

    #[Test]
    public function default_header_slot_remains_non_sticky_when_navbar_is_static(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $header = $this->slotType('header', 'Header', 1);
        $main = $this->slotType('main', 'Main', 2);
        $navbarType = $this->blockType('sticky-navbar', 'Navbar', 10);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Static Header Slot Shell',
            'slug' => 'static-header-slot-shell',
            'status' => 'published',
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $site->id, 'name' => 'Static Header Slot Shell', 'slug' => 'static-header-slot-shell', 'path' => '/p/static-header-slot-shell'],
        );

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $header->id,
            'sort_order' => 0,
        ]);

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 1,
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'sticky-navbar',
            'block_type_id' => $navbarType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $header->id,
            'sort_order' => 0,
            'settings' => json_encode(['sticky_mode' => 'static'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => true,
        ]);

        $response = $this->get('/p/static-header-slot-shell');

        $response->assertOk();
        $response->assertSee('<header data-wb-slot="header" class="wb-public-site-header">', false);
        $response->assertSee('<nav class="wb-navbar wb-navbar--static" data-wb-public-block-type="sticky-navbar">', false);
        $response->assertDontSee('wb-cms-navbar--sticky', false);
        $response->assertDontSee('wb-cms-navbar--fixed', false);
    }

    #[Test]
    public function nested_layout_blocks_render_without_extra_wrappers(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $main = $this->slotType('main', 'Main', 1);
        $sectionType = $this->blockType('section', 'Section', 1);
        $containerType = $this->blockType('container', 'Container', 2);
        $cardType = $this->blockType('card', 'Card', 3);
        $alertType = $this->blockType('alert', 'Alert', 4);
        $gridType = $this->blockType('grid', 'Grid', 5);
        $headerType = $this->blockType('header', 'Header', 6);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $site->id, 'name' => 'About', 'slug' => 'about', 'path' => '/p/about'],
        );

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $section = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $container = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $section->id,
            'type' => 'container',
            'block_type_id' => $containerType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $card = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $container->id,
            'type' => 'card',
            'block_type_id' => $cardType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $card->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Feature card',
            'content' => 'Card content rendered before the alert.',
        ]);

        $alert = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $container->id,
            'type' => 'alert',
            'block_type_id' => $alertType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 1,
            'settings' => json_encode(['variant' => 'info'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $alert->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Spacing proof',
            'content' => 'Alert content follows the card inside the same container flow.',
        ]);

        $grid = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $section->id,
            'type' => 'grid',
            'block_type_id' => $gridType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 1,
            'settings' => json_encode(['columns' => '2'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $gridHeader = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $grid->id,
            'type' => 'header',
            'block_type_id' => $headerType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'variant' => 'h2',
            'status' => 'published',
            'is_system' => false,
        ]);

        $gridHeader->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Grid child heading',
        ]);

        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($card->fresh(['textTranslations']));
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($alert->fresh(['textTranslations']));
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($gridHeader->fresh(['textTranslations']));

        $response = $this->get('/p/about');

        $response->assertOk();
        $response->assertSeeInOrder([
            '<main data-wb-slot="main" id="main-content">',
            '<section class="wb-section wb-stack" data-wb-public-block-type="section">',
            '<div class="wb-container wb-stack" data-wb-public-block-type="container">',
            '<article class="wb-card" data-wb-public-block-type="card">',
            '<div class="wb-alert wb-alert-info">',
            '<h3 class="wb-alert-title">Spacing proof</h3>',
            '<p>Alert content follows the card inside the same container flow.</p>',
            '</div>',
            '<div class="wb-grid wb-grid-2" data-wb-public-block-type="grid">',
            '<h2 data-wb-public-block-type="header">Grid child heading</h2>',
            '</section>',
            '</main>',
        ], false);
        $response->assertDontSee('wb-alert wb-alert-info wb-stack', false);
        $response->assertDontSee('wb-grid wb-stack', false);
        $response->assertDontSee('wb-stack wb-gap-3', false);
    }

    #[Test]
    public function empty_slots_are_still_rendered(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $header = $this->slotType('header', 'Header', 1);
        $main = $this->slotType('main', 'Main', 2);
        $footer = $this->slotType('footer', 'Footer', 3);
        $plainTextType = $this->blockType('plain_text', 'Plain Text', 1);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'published',
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $site->id, 'name' => 'Home', 'slug' => 'home', 'path' => '/'],
        );

        foreach ([[$header, 0], [$main, 1], [$footer, 2]] as [$slotType, $sortOrder]) {
            PageSlot::query()->create([
                'page_id' => $page->id,
                'slot_type_id' => $slotType->id,
                'sort_order' => $sortOrder,
            ]);
        }

        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'plain_text',
            'block_type_id' => $plainTextType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);
        $block->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'content' => 'Main slot content',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<header data-wb-slot="header" class="wb-public-site-header">', false);
        $response->assertSee('<footer data-wb-slot="footer">', false);
        $response->assertDontSee('This page has no published content yet');
    }

    #[Test]
    public function legacy_container_blocks_still_render_stack_flow_by_default(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $main = SlotType::query()->updateOrCreate(
            ['slug' => 'main'],
            ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Container Legacy',
            'slug' => 'container-legacy',
            'status' => 'published',
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $site->id, 'name' => 'Container Legacy', 'slug' => 'container-legacy', 'path' => '/p/container-legacy'],
        );

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $container = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'container',
            'block_type_id' => BlockType::query()->updateOrCreate(
                ['slug' => 'container'],
                ['name' => 'Container', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1, 'is_system' => false, 'is_container' => true],
            )->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $header = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $container->id,
            'type' => 'header',
            'block_type_id' => BlockType::query()->updateOrCreate(
                ['slug' => 'header'],
                ['name' => 'Header', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 2, 'is_system' => false, 'is_container' => false],
            )->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'variant' => 'h2',
            'status' => 'published',
            'is_system' => false,
        ]);
        $header->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Legacy container heading',
        ]);

        $response = $this->get('/p/container-legacy');

        $response->assertOk();
        $response->assertSee('<div class="wb-container wb-stack" data-wb-public-block-type="container">', false);
        $response->assertSee('Legacy container heading', false);
    }

    private function buildHomepageWithHeaderSidebarAndFooter(): Page
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $header = $this->slotType('header', 'Header', 1);
        $main = $this->slotType('main', 'Main', 2);
        $sidebar = $this->slotType('sidebar', 'Sidebar', 3);
        $footer = $this->slotType('footer', 'Footer', 4);
        $headerType = $this->blockType('header', 'Header', 1);
        $plainTextType = $this->blockType('plain_text', 'Plain Text', 2);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'published',
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $site->id, 'name' => 'Home', 'slug' => 'home', 'path' => '/'],
        );

        foreach ([[$header, 0], [$main, 1], [$sidebar, 2], [$footer, 3]] as [$slotType, $sortOrder]) {
            PageSlot::query()->create([
                'page_id' => $page->id,
                'slot_type_id' => $slotType->id,
                'sort_order' => $sortOrder,
            ]);
        }

        $headerBlock = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'header',
            'block_type_id' => $headerType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $header->id,
            'sort_order' => 0,
            'variant' => 'h1',
            'status' => 'published',
            'is_system' => false,
        ]);
        $headerBlock->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'WebBlocks CMS',
        ]);

        foreach ([
            ['slot' => $main, 'order' => 0, 'content' => 'Main slot content'],
            ['slot' => $sidebar, 'order' => 0, 'content' => 'Sidebar supporting content'],
            ['slot' => $footer, 'order' => 0, 'content' => 'Footer supporting content'],
        ] as $definition) {
            $block = Block::query()->create([
                'page_id' => $page->id,
                'type' => 'plain_text',
                'block_type_id' => $plainTextType->id,
                'source_type' => 'static',
                'slot' => $definition['slot']->slug,
                'slot_type_id' => $definition['slot']->id,
                'sort_order' => $definition['order'],
                'status' => 'published',
                'is_system' => false,
            ]);
            $block->textTranslations()->create([
                'locale_id' => Page::defaultLocaleId(),
                'content' => $definition['content'],
            ]);
            app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));
        }

        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($headerBlock->fresh(['textTranslations']));

        return $page;
    }

    private function slotType(string $slug, string $name, int $sortOrder): SlotType
    {
        return SlotType::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'sort_order' => $sortOrder, 'status' => 'published'],
        );
    }

    private function blockType(string $slug, string $name, int $sortOrder): BlockType
    {
        return BlockType::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'source_type' => 'static', 'status' => 'published', 'sort_order' => $sortOrder],
        );
    }

    private function headHtml(string $html): string
    {
        $start = strpos($html, '<head>');
        $end = strpos($html, '</head>');

        $this->assertNotFalse($start, 'Failed asserting that the response contains a <head> element.');
        $this->assertNotFalse($end, 'Failed asserting that the response contains a </head> element.');

        return substr($html, $start, $end - $start);
    }

    private function bodyHtml(string $html): string
    {
        $start = strpos($html, '<body');
        $end = strpos($html, '</body>');

        $this->assertNotFalse($start, 'Failed asserting that the response contains a <body> element.');
        $this->assertNotFalse($end, 'Failed asserting that the response contains a </body> element.');

        return substr($html, $start, $end - $start);
    }

    private function assertStringContainsInOrder(string $haystack, array $needles): void
    {
        $offset = 0;

        foreach ($needles as $needle) {
            $position = strpos($haystack, $needle, $offset);

            $this->assertNotFalse($position, "Failed asserting that [{$needle}] appears after the previous asset.");

            $offset = $position + strlen($needle);
        }
    }
}
