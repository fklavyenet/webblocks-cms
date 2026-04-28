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
