<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use App\Support\Pages\PublicLayoutMode;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicLayoutModeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function default_pages_use_stack_mode(): void
    {
        $page = $this->buildPage('about');

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<main data-wb-slot="main" id="main-content">', false);
        $response->assertDontSee('class="wb-container wb-container-lg"', false);
        $response->assertDontSee('<div class="wb-stack wb-gap-6">', false);
        $response->assertDontSee('wb-content-shell', false);
        $this->assertSame(PublicLayoutMode::STACK, PublicLayoutMode::forPage($page->fresh()->load(['slots.slotType', 'blocks'])));
    }

    #[Test]
    public function pages_with_sidebar_slot_use_sidebar_composition(): void
    {
        $page = $this->buildPage('contact', withSidebar: true);

        $response = $this->get(route('pages.show', 'contact'));

        $response->assertOk();
        $response->assertSee('<aside data-wb-slot="sidebar">', false);
        $response->assertSee('Sidebar supporting content');
        $response->assertDontSee('<div class="wb-stack wb-gap-4">', false);
        $response->assertDontSee('wb-grid wb-grid-4 wb-gap-6', false);
        $response->assertDontSee('wb-public-sidebar', false);
        $this->assertSame(PublicLayoutMode::SIDEBAR, PublicLayoutMode::forPage($page->fresh()->load(['slots.slotType', 'blocks'])));
    }

    #[Test]
    public function content_mode_not_enabled_without_reliable_metadata(): void
    {
        $page = $this->buildPage('journal', pageType: 'blog');

        $response = $this->get(route('pages.show', 'journal'));

        $response->assertOk();
        $response->assertDontSee('wb-content-shell', false);
        $response->assertDontSee('wb-content-body', false);
        $this->assertSame(PublicLayoutMode::STACK, PublicLayoutMode::forPage($page->fresh()->load(['slots.slotType', 'blocks'])));
    }

    #[Test]
    public function layout_mode_helper_returns_expected_values(): void
    {
        $stackPage = $this->buildPage('plain-page');
        $sidebarPage = $this->buildPage('support', withSidebar: true);
        $blogPage = $this->buildPage('notes', pageType: 'blog');

        $this->assertSame(PublicLayoutMode::STACK, PublicLayoutMode::forPage($stackPage->fresh()->load(['slots.slotType', 'blocks'])));
        $this->assertSame(PublicLayoutMode::SIDEBAR, PublicLayoutMode::forPage($sidebarPage->fresh()->load(['slots.slotType', 'blocks'])));
        $this->assertSame(PublicLayoutMode::STACK, PublicLayoutMode::forPage($blogPage->fresh()->load(['slots.slotType', 'blocks'])));
    }

    private function buildPage(string $slug, bool $withSidebar = false, string $pageType = 'default'): Page
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $main = $this->slotType('main', 'Main', 2);
        $textType = $this->blockType('text', 'Text', 2);
        $sidebar = $withSidebar ? $this->slotType('sidebar', 'Sidebar', 3) : null;

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
            'page_type' => $pageType,
            'status' => 'published',
        ]);

        PageTranslation::query()->updateOrCreate(
            [
                'page_id' => $page->id,
                'locale_id' => Page::defaultLocaleId(),
            ],
            [
                'site_id' => $site->id,
                'name' => str($slug)->replace('-', ' ')->title()->toString(),
                'slug' => $slug,
                'path' => '/p/'.$slug,
            ]
        );

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
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

        if ($sidebar) {
            PageSlot::query()->create([
                'page_id' => $page->id,
                'slot_type_id' => $sidebar->id,
                'sort_order' => 1,
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
        }

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
