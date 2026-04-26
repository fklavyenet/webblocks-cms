<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\Site;
use App\Models\SlotType;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicEditorialBlocksRenderingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function list_block_renders_ordered_items_from_line_delimited_content(): void
    {
        $page = $this->pageWithMainSlot();

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'list',
            'block_type_id' => $this->blockType('list', 'List', 1)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Launch checklist',
            'content' => "Provision site\nSeed content\nPublish page",
            'variant' => 'ordered',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('Launch checklist');
        $response->assertSee('<ol class="wb-stack wb-gap-1">', false);
        $response->assertSee('<li>Provision site</li>', false);
        $response->assertSee('<li>Seed content</li>', false);
        $response->assertSee('<li>Publish page</li>', false);
    }

    #[Test]
    public function list_block_preserves_legacy_settings_items(): void
    {
        $page = $this->pageWithMainSlot();

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'list',
            'block_type_id' => $this->blockType('list', 'List', 1)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Migrated list',
            'content' => 'This should not render',
            'settings' => json_encode([
                'items' => [
                    ['label' => 'Existing setting item'],
                    ['title' => 'Second setting item'],
                ],
            ], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('Existing setting item');
        $response->assertSee('Second setting item');
    }

    #[Test]
    public function table_block_renders_header_row_from_line_delimited_content(): void
    {
        $page = $this->pageWithMainSlot();

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'table',
            'block_type_id' => $this->blockType('table', 'Table', 2)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Plan matrix',
            'content' => "Plan | Seats | Support\nStarter | 3 | Email\nScale | 10 | Priority",
            'variant' => 'header-row',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('wb-table-wrap', false);
        $response->assertSee('<table class="wb-table">', false);
        $response->assertSee('<th>Plan</th>', false);
        $response->assertSee('<th>Seats</th>', false);
        $response->assertSee('<td>Starter</td>', false);
        $response->assertSee('<td>Priority</td>', false);
    }

    #[Test]
    public function table_block_preserves_legacy_settings_rows(): void
    {
        $page = $this->pageWithMainSlot();

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'table',
            'block_type_id' => $this->blockType('table', 'Table', 2)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'content' => 'Legacy content should not render',
            'settings' => json_encode([
                'rows' => [
                    ['columns' => [['label' => 'Name'], ['label' => 'Role']]],
                    ['columns' => [['label' => 'Ava'], ['label' => 'Editor']]],
                ],
            ], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<th>Name</th>', false);
        $response->assertSee('<td>Ava</td>', false);
        $response->assertSee('<td>Editor</td>', false);
    }

    #[Test]
    public function related_content_block_renders_editorial_links_as_webblocks_link_list(): void
    {
        $page = $this->pageWithMainSlot();

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'related-content',
            'block_type_id' => $this->blockType('related-content', 'Related Content', 3, true, 'data')->id,
            'source_type' => 'data',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Keep reading',
            'subtitle' => 'Browse the next resources.',
            'content' => "Getting Started | /docs/start | Guide | Basics and setup\nAPI Reference | /docs/api | Docs | Endpoints and payloads",
            'status' => 'published',
            'is_system' => true,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('Keep reading');
        $response->assertSee('Browse the next resources.');
        $response->assertSee('wb-link-list', false);
        $response->assertSee('wb-link-list-item', false);
        $response->assertSee('<a href="/docs/start" class="wb-link-list-title">Getting Started</a>', false);
        $response->assertSee('wb-link-list-meta', false);
        $response->assertSee('Guide');
        $response->assertSee('wb-link-list-desc', false);
        $response->assertSee('Endpoints and payloads');
    }

    #[Test]
    public function related_content_block_falls_back_to_automatic_related_pages_when_editorial_links_are_blank(): void
    {
        $page = $this->pageWithMainSlot('Docs Home', 'docs-home', 'docs');
        $this->pageWithMainSlot('Getting Started', 'getting-started', 'docs');
        $this->pageWithMainSlot('API Reference', 'api-reference', 'docs');

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'related-content',
            'block_type_id' => $this->blockType('related-content', 'Related Content', 3, true, 'data')->id,
            'source_type' => 'data',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Related docs',
            'content' => '',
            'status' => 'published',
            'is_system' => true,
        ]);

        $response = $this->get(route('pages.show', 'docs-home'));

        $response->assertOk();
        $response->assertSee('Related docs');
        $response->assertSee('<a href="/p/getting-started" class="wb-link-list-title">Getting Started</a>', false);
        $response->assertSee('<a href="/p/api-reference" class="wb-link-list-title">API Reference</a>', false);
    }

    private function pageWithMainSlot(string $title = 'About', string $slug = 'about', string $pageType = 'default'): Page
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $site = Site::query()->firstOrFail();

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => $title,
            'slug' => $slug,
            'page_type' => $pageType,
            'status' => 'published',
        ]);

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

    private function blockType(string $slug, string $name, int $sortOrder, bool $isSystem = false, string $sourceType = 'static'): BlockType
    {
        return BlockType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'source_type' => $sourceType, 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => $isSystem],
        );
    }
}
