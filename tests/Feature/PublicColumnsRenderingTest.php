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

class PublicColumnsRenderingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function columns_cards_variant_renders_child_items_as_cards(): void
    {
        $page = $this->pageWithMainSlot();
        $columns = $this->columnsBlock($page, 'cards');

        $this->columnItem($page, $columns, 'Fast setup', 'Start with meaningful defaults.');
        $this->columnItem($page, $columns, 'Editor friendly', 'Add and manage reusable content.');

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('wb-grid wb-grid-2', false);
        $response->assertSee('wb-card-body wb-stack wb-gap-2', false);
        $response->assertDontSee('wb-prose', false);
        $response->assertDontSee('wb-link-list', false);
        $response->assertSee('Fast setup');
        $response->assertSee('Editor friendly');
    }

    #[Test]
    public function columns_stats_variant_renders_webblocks_stats_markup(): void
    {
        $page = $this->pageWithMainSlot();
        $columns = $this->columnsBlock($page, 'stats');

        $this->columnItem($page, $columns, 'Active sites', '24');
        $this->columnItem($page, $columns, 'Published pages', '128');

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('wb-stat', false);
        $response->assertSee('wb-stat-label', false);
        $response->assertSee('wb-stat-value', false);
        $response->assertSee('Active sites');
        $response->assertSee('24');
        $response->assertDontSee('wb-link-list', false);
    }

    #[Test]
    public function columns_links_variant_renders_webblocks_link_list_markup(): void
    {
        $page = $this->pageWithMainSlot();
        $columns = $this->columnsBlock($page, 'links');

        $this->columnItem($page, $columns, 'Documentation', 'Implementation notes and usage guidance.', '/docs');
        $this->columnItem($page, $columns, 'Support', 'Talk to the team.', '/support');

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('wb-link-list', false);
        $response->assertSee('wb-link-list-item', false);
        $response->assertSee('wb-link-list-title', false);
        $response->assertSee('wb-link-list-desc', false);
        $response->assertSee('<a href="/docs" class="wb-link-list-title">Documentation</a>', false);
        $response->assertDontSee('wb-grid wb-grid-2', false);
    }

    private function pageWithMainSlot(): Page
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $site = Site::query()->firstOrFail();

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
        ]);

        return $page;
    }

    private function columnsBlock(Page $page, string $variant): Block
    {
        return Block::query()->create([
            'page_id' => $page->id,
            'type' => 'columns',
            'block_type_id' => $this->blockType('columns', 'Columns', 1, true)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Columns block',
            'subtitle' => 'Structured content',
            'content' => 'Public columns content',
            'variant' => $variant,
            'status' => 'published',
            'is_system' => true,
        ]);
    }

    private function columnItem(Page $page, Block $parent, string $title, string $content, ?string $url = null): Block
    {
        return Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $parent->id,
            'type' => 'column_item',
            'block_type_id' => $this->blockType('column_item', 'Column Item', 2, true)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => Block::query()->where('parent_id', $parent->id)->count(),
            'title' => $title,
            'content' => $content,
            'url' => $url,
            'status' => 'published',
            'is_system' => false,
        ]);
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
            ['name' => $name, 'source_type' => 'static', 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => $isSystem],
        );
    }
}
