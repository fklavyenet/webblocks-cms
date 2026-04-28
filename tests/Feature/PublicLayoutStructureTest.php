<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
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
        $response->assertSee('class="wb-public-body"', false);
        $response->assertSeeInOrder([
            '<header data-wb-slot="header">',
            '<main data-wb-slot="main" id="main-content">',
            '<aside data-wb-slot="sidebar">',
            '<footer data-wb-slot="footer">',
        ], false);
    }

    #[Test]
    public function main_slot_renders_direct_block_output(): void
    {
        $this->buildHomepageWithHeaderSidebarAndFooter();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<main data-wb-slot="main" id="main-content">', false);
        $response->assertSee('Main slot content');
        $response->assertDontSee('wb-container wb-container-lg', false);
        $response->assertDontSee('wb-stack wb-gap-6', false);
    }

    #[Test]
    public function header_slot_uses_semantic_wrapper_only(): void
    {
        $this->buildHomepageWithHeaderSidebarAndFooter();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<header data-wb-slot="header">', false);
        $response->assertDontSee('wb-public-header', false);
    }

    #[Test]
    public function sidebar_slot_renders_without_shell_wrappers(): void
    {
        $this->buildHomepageWithHeaderSidebarAndFooter();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<aside data-wb-slot="sidebar">', false);
        $response->assertSee('Sidebar supporting content');
        $response->assertDontSee('wb-public-sidebar', false);
    }

    #[Test]
    public function footer_slot_renders_without_fallback_chrome(): void
    {
        $this->buildHomepageWithHeaderSidebarAndFooter();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<footer data-wb-slot="footer">', false);
        $response->assertSee('Footer supporting content');
        $response->assertDontSee('wb-public-footer', false);
        $response->assertDontSee('Cookie settings', false);
    }

    #[Test]
    public function empty_slots_are_still_rendered(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $header = $this->slotType('header', 'Header', 1);
        $main = $this->slotType('main', 'Main', 2);
        $footer = $this->slotType('footer', 'Footer', 3);
        $textType = $this->blockType('text', 'Text', 1);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'published',
        ]);

        PageTranslation::query()->updateOrCreate(
            [
                'page_id' => $page->id,
                'locale_id' => Page::defaultLocaleId(),
            ],
            [
                'site_id' => $site->id,
                'name' => 'Home',
                'slug' => 'home',
                'path' => '/',
            ]
        );

        foreach ([[$header, 0], [$main, 1], [$footer, 2]] as [$slotType, $sortOrder]) {
            PageSlot::query()->create([
                'page_id' => $page->id,
                'slot_type_id' => $slotType->id,
                'sort_order' => $sortOrder,
            ]);
        }

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'text',
            'block_type_id' => $textType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'content' => 'Main slot content',
            'status' => 'published',
            'is_system' => false,
        ]);

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
        $headingType = $this->blockType('heading', 'Heading', 1);
        $textType = $this->blockType('text', 'Text', 2);
        $buttonType = $this->blockType('button', 'Button', 3);
        $navigationType = $this->blockType('navigation-auto', 'Navigation Auto', 4, 'navigation', true);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'published',
        ]);

        PageTranslation::query()->updateOrCreate(
            [
                'page_id' => $page->id,
                'locale_id' => Page::defaultLocaleId(),
            ],
            [
                'site_id' => $site->id,
                'name' => 'Home',
                'slug' => 'home',
                'path' => '/',
            ]
        );

        foreach ([
            [$header, 0],
            [$main, 1],
            [$sidebar, 2],
            [$footer, 3],
        ] as [$slotType, $sortOrder]) {
            PageSlot::query()->create([
                'page_id' => $page->id,
                'slot_type_id' => $slotType->id,
                'sort_order' => $sortOrder,
            ]);
        }

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'heading',
            'block_type_id' => $headingType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $header->id,
            'sort_order' => 0,
            'title' => 'WebBlocks CMS',
            'variant' => 'h1',
            'status' => 'published',
            'is_system' => false,
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'navigation-auto',
            'block_type_id' => $navigationType->id,
            'source_type' => 'navigation',
            'slot' => 'header',
            'slot_type_id' => $header->id,
            'sort_order' => 1,
            'settings' => json_encode(['menu_key' => 'primary'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => true,
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'button',
            'block_type_id' => $buttonType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $header->id,
            'sort_order' => 2,
            'title' => 'Contact',
            'url' => '/p/contact',
            'variant' => 'primary',
            'status' => 'published',
            'is_system' => false,
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'text',
            'block_type_id' => $textType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'content' => 'Main slot content',
            'status' => 'published',
            'is_system' => false,
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'text',
            'block_type_id' => $textType->id,
            'source_type' => 'static',
            'slot' => 'sidebar',
            'slot_type_id' => $sidebar->id,
            'sort_order' => 0,
            'content' => 'Sidebar supporting content',
            'status' => 'published',
            'is_system' => false,
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'text',
            'block_type_id' => $textType->id,
            'source_type' => 'static',
            'slot' => 'footer',
            'slot_type_id' => $footer->id,
            'sort_order' => 0,
            'content' => 'Footer supporting content',
            'status' => 'published',
            'is_system' => false,
        ]);

        return $page;
    }

    private function slotType(string $slug, string $name, int $sortOrder): SlotType
    {
        return SlotType::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'sort_order' => $sortOrder,
                'status' => 'published',
            ]
        );
    }

    private function blockType(string $slug, string $name, int $sortOrder, string $sourceType = 'static', bool $isSystem = false): BlockType
    {
        return BlockType::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'source_type' => $sourceType,
                'status' => 'published',
                'sort_order' => $sortOrder,
                'is_system' => $isSystem,
            ]
        );
    }
}
