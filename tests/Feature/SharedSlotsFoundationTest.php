<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\SharedSlot;
use App\Models\SharedSlotBlock;
use App\Models\Site;
use App\Models\SlotType;
use App\Support\Blocks\BlockTranslationWriter;
use App\Support\Pages\PublicPagePresenter;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SharedSlotsFoundationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function migrations_create_shared_slot_tables_and_page_slot_source_columns(): void
    {
        $this->assertTrue(Schema::hasTable('shared_slots'));
        $this->assertTrue(Schema::hasTable('shared_slot_blocks'));
        $this->assertTrue(Schema::hasColumn('page_slots', 'source_type'));
        $this->assertTrue(Schema::hasColumn('page_slots', 'shared_slot_id'));
    }

    #[Test]
    public function existing_page_slots_are_backfilled_to_page_source_type_during_migration(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $slotType = SlotType::query()->updateOrCreate(
            ['slug' => 'main'],
            ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Legacy Page',
            'slug' => 'legacy-page',
            'status' => Page::STATUS_DRAFT,
        ]);

        PageSlot::withoutEvents(function () use ($page, $slotType): void {
            PageSlot::query()->insert([
                'page_id' => $page->id,
                'slot_type_id' => $slotType->id,
                'sort_order' => 0,
                'settings' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $migration = require database_path('migrations/2026_05_05_120000_add_shared_slots_foundation.php');
        $migration->down();

        $this->assertFalse(Schema::hasTable('shared_slots'));
        $this->assertFalse(Schema::hasColumn('page_slots', 'source_type'));

        $migration->up();

        $this->assertDatabaseHas('page_slots', [
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'shared_slot_id' => null,
        ]);
    }

    #[Test]
    public function page_slot_source_type_accepts_only_supported_values(): void
    {
        $this->assertSame(
            ['page', 'shared_slot', 'disabled'],
            PageSlot::sourceTypes(),
        );

        $this->assertSame('page', PageSlot::normalizeSourceType('page'));
        $this->assertSame('shared_slot', PageSlot::normalizeSourceType('shared_slot'));
        $this->assertSame('disabled', PageSlot::normalizeSourceType('disabled'));

        $this->expectException(InvalidArgumentException::class);

        PageSlot::normalizeSourceType('invalid');
    }

    #[Test]
    public function page_owned_slot_behavior_remains_unchanged(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $slotType = $this->slotType('main', 'Main', 1);
        $blockType = $this->blockType('plain_text', 'Plain Text', 1);
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => Page::STATUS_PUBLISHED,
        ]);

        $slot = PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'sort_order' => 0,
        ]);

        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'plain_text',
            'block_type_id' => $blockType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'content' => 'Page-owned slot content',
            'status' => 'published',
            'is_system' => false,
        ]);

        $presented = app(PublicPagePresenter::class)->present($page->fresh()->load(['slots.slotType', 'blocks']));

        $this->assertTrue($slot->fresh()->usesPageOwnedBlocks());
        $this->assertCount(1, $slot->fresh()->blocks()->get());
        $this->assertCount(1, $presented['slots']);
        $this->assertCount(1, $presented['slots'][0]['blocks']);
        $this->assertSame($block->id, $presented['slots'][0]['blocks'][0]->id);
    }

    #[Test]
    public function published_page_renders_existing_page_owned_header_sidebar_and_main_slot_blocks(): void
    {
        $page = $this->publishedPageWithSlots();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSeeInOrder([
            '<aside data-wb-slot="sidebar" id="docsSidebar" class="wb-sidebar">',
            'Shared Slots Regression Sidebar',
            '<nav data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full">',
            'Shared Slots Regression Header',
            '<main data-wb-slot="main" id="main-content" class="wb-dashboard-main">',
            'Shared Slots Regression Main',
        ], false);
    }

    #[Test]
    public function page_source_type_renders_page_owned_slot_blocks_in_public_output(): void
    {
        $page = $this->publishedPageWithSlots([
            'header' => PageSlot::SOURCE_TYPE_PAGE,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Shared Slots Regression Header', false);
        $response->assertSee('Shared Slots Regression Main', false);
        $response->assertSee('Shared Slots Regression Sidebar', false);
    }

    #[Test]
    public function missing_source_type_attribute_is_treated_as_page_owned_for_rendering_compatibility(): void
    {
        $page = $this->publishedPageWithSlots();

        $headerSlot = PageSlot::query()->where('page_id', $page->id)
            ->whereHas('slotType', fn ($query) => $query->where('slug', 'header'))
            ->firstOrFail();

        $legacySlot = new PageSlot;
        $legacySlot->setRawAttributes(Arr::except($headerSlot->getAttributes(), ['source_type']), true);
        $legacySlot->setRelation('slotType', $headerSlot->slotType);
        $legacySlot->setRelation('page', $headerSlot->page);

        $this->assertTrue($legacySlot->usesPageOwnedBlocks());

        $presented = app(PublicPagePresenter::class)->present($page->fresh()->load(['slots.slotType', 'blocks']));

        $this->assertCount(1, $legacySlot->blocks()->get());
        $this->assertCount(1, $presented['slots'][1]['blocks']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Shared Slots Regression Header', false);
    }

    #[Test]
    public function disabled_source_type_renders_no_slot_blocks(): void
    {
        $this->publishedPageWithSlots([
            'header' => PageSlot::SOURCE_TYPE_DISABLED,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<nav data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full">', false);
        $response->assertDontSee('Shared Slots Regression Header', false);
        $response->assertSee('Shared Slots Regression Main', false);
        $response->assertSee('Shared Slots Regression Sidebar', false);
    }

    #[Test]
    public function shared_slot_source_type_does_not_accidentally_render_page_owned_blocks_yet(): void
    {
        $this->publishedPageWithSlots([
            'header' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<nav data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full">', false);
        $response->assertDontSee('Shared Slots Regression Header', false);
        $response->assertSee('Shared Slots Regression Main', false);
        $response->assertSee('Shared Slots Regression Sidebar', false);
    }

    #[Test]
    public function shared_slots_are_site_scoped_and_relationships_resolve(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $otherSite = Site::query()->create([
            'name' => 'Second Site',
            'handle' => 'second-site',
            'domain' => 'second.example.test',
            'is_primary' => false,
        ]);
        $slotType = $this->slotType('main', 'Main', 1);
        $blockType = $this->blockType('plain_text', 'Plain Text', 1);
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Home',
            'slug' => 'home',
            'status' => Page::STATUS_DRAFT,
        ]);
        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'plain_text',
            'block_type_id' => $blockType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'content' => 'Reusable block',
            'status' => 'published',
            'is_system' => false,
        ]);

        $sharedSlot = SharedSlot::query()->create([
            'site_id' => $site->id,
            'name' => 'Global Main',
            'handle' => 'global-main',
            'slot_name' => 'main',
            'public_shell' => 'default',
            'is_active' => true,
        ]);

        $sharedSlotBlock = SharedSlotBlock::query()->create([
            'shared_slot_id' => $sharedSlot->id,
            'block_id' => $block->id,
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $pageSlot = PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
            'shared_slot_id' => $sharedSlot->id,
            'sort_order' => 0,
        ]);

        $this->assertTrue($site->sharedSlots->contains(fn (SharedSlot $candidate) => $candidate->id === $sharedSlot->id));
        $this->assertFalse($otherSite->sharedSlots->contains(fn (SharedSlot $candidate) => $candidate->id === $sharedSlot->id));
        $this->assertSame($site->id, $sharedSlot->site->id);
        $this->assertSame($sharedSlot->id, $sharedSlotBlock->sharedSlot->id);
        $this->assertSame($block->id, $sharedSlotBlock->block->id);
        $this->assertSame($sharedSlot->id, $pageSlot->sharedSlot->id);
        $this->assertSame($pageSlot->id, $sharedSlot->pageSlots->firstOrFail()->id);
        $this->assertSame($sharedSlotBlock->id, $sharedSlot->slotBlocks->firstOrFail()->id);
        $this->assertSame($block->id, $sharedSlot->blocks->firstOrFail()->id);
        $this->assertFalse($pageSlot->usesPageOwnedBlocks());
        $this->assertCount(0, $pageSlot->blocks()->get());
    }

    private function slotType(string $slug, string $name, int $sortOrder): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => true],
        );
    }

    private function blockType(string $slug, string $name, int $sortOrder): BlockType
    {
        return BlockType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'source_type' => 'static', 'status' => 'published', 'sort_order' => $sortOrder],
        );
    }

    private function publishedPageWithSlots(array $slotSourceTypes = []): Page
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $header = $this->slotType('header', 'Header', 1);
        $main = $this->slotType('main', 'Main', 2);
        $sidebar = $this->slotType('sidebar', 'Sidebar', 3);
        $headerType = $this->blockType('header', 'Header', 1);
        $plainTextType = $this->blockType('plain_text', 'Plain Text', 2);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Home',
            'slug' => 'home',
            'status' => Page::STATUS_PUBLISHED,
            'settings' => ['public_shell' => 'docs'],
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $site->id, 'name' => 'Home', 'slug' => 'home', 'path' => '/'],
        );

        $sharedHeaderSlot = SharedSlot::query()->create([
            'site_id' => $site->id,
            'name' => 'Shared Header',
            'handle' => 'shared-header',
            'slot_name' => 'header',
            'public_shell' => 'docs',
            'is_active' => true,
        ]);

        foreach ([['type' => $header, 'order' => 0], ['type' => $main, 'order' => 1], ['type' => $sidebar, 'order' => 2]] as $definition) {
            $slug = $definition['type']->slug;
            $sourceType = $slotSourceTypes[$slug] ?? PageSlot::SOURCE_TYPE_PAGE;

            PageSlot::query()->create([
                'page_id' => $page->id,
                'slot_type_id' => $definition['type']->id,
                'source_type' => $sourceType,
                'shared_slot_id' => $sourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT ? $sharedHeaderSlot->id : null,
                'sort_order' => $definition['order'],
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
            'title' => 'Shared Slots Regression Header',
        ]);

        foreach ([
            ['slot' => $main, 'order' => 0, 'content' => 'Shared Slots Regression Main'],
            ['slot' => $sidebar, 'order' => 0, 'content' => 'Shared Slots Regression Sidebar'],
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
}
