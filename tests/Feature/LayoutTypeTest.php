<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\LayoutType;
use App\Models\LayoutTypeSlot;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use App\Models\User;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Database\Seeders\LayoutTypeSeeder;
use Database\Seeders\SlotTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LayoutTypeTest extends TestCase
{
    use RefreshDatabase;

    private function seedLayoutFoundation(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(SlotTypeSeeder::class);
        $this->seed(BlockTypeSeeder::class);
        $this->seed(LayoutTypeSeeder::class);
    }

    #[Test]
    public function layout_type_seeder_is_idempotent_and_creates_docs_layout_slots(): void
    {
        $this->seedLayoutFoundation();
        $this->seed(LayoutTypeSeeder::class);

        $docsLayout = LayoutType::query()->where('slug', 'docs')->firstOrFail();

        $this->assertSame('docs', $docsLayout->publicShellPreset());
        $this->assertSame(4, $docsLayout->slots()->count());
        $this->assertSame(1, LayoutType::query()->where('slug', 'docs')->count());
        $this->assertDatabaseHas('layout_type_slots', [
            'layout_type_id' => $docsLayout->id,
            'ownership' => LayoutTypeSlot::OWNERSHIP_LAYOUT,
            'wrapper_preset' => 'docs-navbar',
        ]);
        $this->assertDatabaseHas('layout_type_slots', [
            'layout_type_id' => $docsLayout->id,
            'ownership' => LayoutTypeSlot::OWNERSHIP_PAGE,
            'wrapper_preset' => 'docs-main',
        ]);
    }

    #[Test]
    public function page_store_persists_layout_type_and_creates_only_page_owned_slots(): void
    {
        $this->seedLayoutFoundation();

        $user = User::factory()->superAdmin()->create();
        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $docsLayout = LayoutType::query()->where('slug', 'docs')->firstOrFail();

        $response = $this->actingAs($user)->post(route('admin.pages.store'), [
            'site_id' => $site->id,
            'title' => 'Getting Started',
            'slug' => 'getting-started',
            'layout_type_id' => $docsLayout->id,
        ]);

        $page = Page::query()->where('layout_type_id', $docsLayout->id)->firstOrFail();
        $mainSlotType = SlotType::query()->where('slug', 'main')->firstOrFail();
        $mainPageSlot = PageSlot::query()->where('page_id', $page->id)->where('slot_type_id', $mainSlotType->id)->firstOrFail();

        $response->assertRedirect(route('admin.pages.edit', $page));
        $this->assertSame($docsLayout->id, $page->layout_type_id);
        $this->assertSame(['main'], $page->slots()->with('slotType')->get()->pluck('slotType.slug')->all());

        $updateResponse = $this->actingAs($user)->put(route('admin.pages.update', $page), [
            'site_id' => $site->id,
            'title' => 'Getting Started',
            'slug' => 'getting-started',
            'layout_type_id' => $docsLayout->id,
        ]);

        $updateResponse->assertRedirect(route('admin.pages.edit', $page));
        $this->assertSame(1, PageSlot::query()->where('page_id', $page->id)->where('slot_type_id', $mainSlotType->id)->count());
        $this->assertSame($mainPageSlot->id, PageSlot::query()->where('page_id', $page->id)->where('slot_type_id', $mainSlotType->id)->value('id'));
    }

    #[Test]
    public function page_edit_lists_layout_owned_and_page_owned_slots_with_correct_actions(): void
    {
        $this->seedLayoutFoundation();

        $user = User::factory()->superAdmin()->create();
        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $docsLayout = LayoutType::query()->where('slug', 'docs')->with('slots.slotType')->firstOrFail();

        $page = Page::query()->create([
            'site_id' => $site->id,
            'layout_type_id' => $docsLayout->id,
            'title' => 'Getting Started',
            'slug' => 'getting-started',
            'status' => 'draft',
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $site->id, 'name' => 'Getting Started', 'slug' => 'getting-started', 'path' => '/p/getting-started'],
        );

        $mainPageSlot = PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => SlotType::query()->where('slug', 'main')->value('id'),
            'sort_order' => 0,
            'settings' => ['wrapper_preset' => 'docs-main', 'wrapper_element' => 'main'],
        ]);

        $headerSlot = $docsLayout->slots->firstWhere('slotType.slug', 'header');
        $sidebarSlot = $docsLayout->slots->firstWhere('slotType.slug', 'sidebar');
        $footerSlot = $docsLayout->slots->firstWhere('slotType.slug', 'footer');

        $response = $this->actingAs($user)->get(route('admin.pages.edit', $page));

        $response->assertOk();
        $response->assertSee('Layout-owned Slots');
        $response->assertSee('Page-owned Slots');
        $response->assertSee('Header is provided by Docs Layout.');
        $response->assertSee('Sidebar is provided by Docs Layout.');
        $response->assertSee('Footer is provided by Docs Layout.');
        $response->assertSee('Main slot');
        $response->assertSee('Edit in Layout');
        $response->assertSee('Edit Blocks');
        $response->assertSee('href="'.route('admin.layout-types.slots.blocks', [$docsLayout, $headerSlot]).'"', false);
        $response->assertSee('href="'.route('admin.layout-types.slots.blocks', [$docsLayout, $sidebarSlot]).'"', false);
        $response->assertSee('href="'.route('admin.layout-types.slots.blocks', [$docsLayout, $footerSlot]).'"', false);
        $response->assertSee('href="'.route('admin.pages.slots.blocks', [$page, $mainPageSlot]).'"', false);
    }

    #[Test]
    public function layout_types_index_renders_and_page_form_contains_layout_type_selector(): void
    {
        $this->seedLayoutFoundation();

        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get(route('admin.layout-types.index'))
            ->assertOk()
            ->assertSee('Layout Types')
            ->assertSee('Docs Layout');

        $this->actingAs($user)
            ->get(route('admin.pages.create'))
            ->assertOk()
            ->assertSee('Layout Type')
            ->assertSee('name="layout_type_id"', false)
            ->assertSee('Docs Layout');
    }

    #[Test]
    public function block_types_index_is_simplified(): void
    {
        $this->seedLayoutFoundation();

        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.block-types.index'));

        $response->assertOk();
        $response->assertSee('<th>Name</th>', false);
        $response->assertSee('<th>Slug</th>', false);
        $response->assertSee('<th>Description</th>', false);
        $response->assertSee('<th>System</th>', false);
        $response->assertDontSee('<th>Blocks</th>', false);
        $response->assertDontSee('<th>Sort Order</th>', false);
    }

    #[Test]
    public function docs_layout_renders_layout_owned_header_sidebar_and_page_owned_main(): void
    {
        $this->seedLayoutFoundation();

        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $docsLayout = LayoutType::query()->where('slug', 'docs')->with('slots.slotType')->firstOrFail();
        $headerSlot = $docsLayout->slots->firstWhere('slotType.slug', 'header');
        $sidebarSlot = $docsLayout->slots->firstWhere('slotType.slug', 'sidebar');
        $mainSlotType = SlotType::query()->where('slug', 'main')->firstOrFail();
        $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

        $pageOne = Page::query()->create([
            'site_id' => $site->id,
            'layout_type_id' => $docsLayout->id,
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'published',
        ]);

        $pageTwo = Page::query()->create([
            'site_id' => $site->id,
            'layout_type_id' => $docsLayout->id,
            'title' => 'Getting Started',
            'slug' => 'getting-started',
            'status' => 'published',
        ]);

        foreach ([[$pageOne, '/'], [$pageTwo, '/p/getting-started']] as [$page, $path]) {
            PageTranslation::query()->updateOrCreate(
                ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
                ['site_id' => $site->id, 'name' => $page->title, 'slug' => $page->slug, 'path' => $path],
            );

            PageSlot::query()->create([
                'page_id' => $page->id,
                'slot_type_id' => $mainSlotType->id,
                'sort_order' => 0,
                'settings' => ['wrapper_preset' => 'docs-main', 'wrapper_element' => 'main'],
            ]);
        }

        Block::query()->create([
            'layout_type_slot_id' => $headerSlot->id,
            'type' => 'header',
            'block_type_id' => $headerType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $headerSlot->slot_type_id,
            'sort_order' => 0,
            'status' => 'published',
            'variant' => 'h1',
            'is_system' => false,
        ])->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Shared Docs Header',
        ]);

        Block::query()->create([
            'layout_type_slot_id' => $sidebarSlot->id,
            'type' => 'plain_text',
            'block_type_id' => $plainTextType->id,
            'source_type' => 'static',
            'slot' => 'sidebar',
            'slot_type_id' => $sidebarSlot->slot_type_id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ])->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'content' => 'Shared sidebar content',
        ]);

        foreach ([[$pageOne, 'Home main content'], [$pageTwo, 'Getting started main content']] as [$page, $content]) {
            Block::query()->create([
                'page_id' => $page->id,
                'type' => 'plain_text',
                'block_type_id' => $plainTextType->id,
                'source_type' => 'static',
                'slot' => 'main',
                'slot_type_id' => $mainSlotType->id,
                'sort_order' => 0,
                'status' => 'published',
                'is_system' => false,
            ])->textTranslations()->create([
                'locale_id' => Page::defaultLocaleId(),
                'content' => $content,
            ]);
        }

        $homeResponse = $this->get('/');
        $docsResponse = $this->get('/p/getting-started');

        $homeResponse->assertOk();
        $homeResponse->assertSee('Shared Docs Header');
        $homeResponse->assertSee('Shared sidebar content');
        $homeResponse->assertSee('Home main content');
        $homeResponse->assertSee('<div class="wb-dashboard-shell">', false);
        $homeResponse->assertSee('data-wb-slot="sidebar" id="docsSidebar" class="wb-sidebar"', false);
        $homeResponse->assertSee('data-wb-slot="header" class="wb-navbar wb-navbar-glass"', false);
        $homeResponse->assertSee('data-wb-slot="main" id="main-content" class="wb-dashboard-main"', false);

        $docsResponse->assertOk();
        $docsResponse->assertSee('Shared Docs Header');
        $docsResponse->assertSee('Shared sidebar content');
        $docsResponse->assertSee('Getting started main content');
        $docsResponse->assertDontSee('Home main content');

        $this->assertSame(1, $pageOne->fresh()->slots()->count());
        $this->assertSame(1, $pageTwo->fresh()->slots()->count());
        $this->assertSame(0, Block::query()->where('page_id', $pageOne->id)->where('slot', 'header')->count());
        $this->assertSame(0, Block::query()->where('page_id', $pageOne->id)->where('slot', 'sidebar')->count());
    }
}
