<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use App\Support\Blocks\BlockTranslationWriter;
use Database\Seeders\FoundationSiteLocaleSeeder;
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
            '<header data-wb-slot="header">',
            '<main data-wb-slot="main" id="main-content">',
            '<aside data-wb-slot="sidebar">',
            '<footer data-wb-slot="footer">',
        ], false);
    }

    #[Test]
    public function public_layout_loads_cms_public_stylesheet_in_head(): void
    {
        $this->buildHomepageWithHeaderSidebarAndFooter();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('assets/webblocks-cms/css/public.css', false);
    }

    #[Test]
    public function slots_render_direct_primitive_block_output_without_extra_shell_wrappers(): void
    {
        $this->buildHomepageWithHeaderSidebarAndFooter();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<h1>WebBlocks CMS</h1>', false);
        $response->assertSee('<p>Main slot content</p>', false);
        $response->assertSee('<p>Sidebar supporting content</p>', false);
        $response->assertSee('<p>Footer supporting content</p>', false);
        $response->assertDontSee('wb-public-header', false);
        $response->assertDontSee('wb-public-sidebar', false);
        $response->assertDontSee('wb-public-footer', false);
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
            '<div class="wb-stack">',
            '<section class="wb-section wb-stack">',
            '<div class="wb-container wb-stack">',
            '<article class="wb-card">',
            '<div class="wb-alert wb-alert-info">',
            '<h3 class="wb-alert-title">Spacing proof</h3>',
            '<p>Alert content follows the card inside the same container flow.</p>',
            '</div>',
            '<div class="wb-grid wb-grid-2">',
            '<h2>Grid child heading</h2>',
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
        $response->assertSee('<header data-wb-slot="header">', false);
        $response->assertSee('<footer data-wb-slot="footer">', false);
        $response->assertDontSee('This page has no published content yet');
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
}
