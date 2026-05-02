<?php

namespace Project\Tests\Feature;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicGettingStartedDocsPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function getting_started_docs_page_renders_in_docs_shell_and_sync_stays_idempotent(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);

        $home = $this->createDocsHomePage();
        $homeBlockCountBefore = $home->blocks()->count();

        $this->artisan('project:sync-ui-docs-getting-started')->assertExitCode(0);
        $this->artisan('project:sync-ui-docs-navigation')->assertExitCode(0);

        $page = Page::query()
            ->whereHas('translations', fn ($query) => $query->where('slug', 'getting-started'))
            ->firstOrFail();

        $response = $this->get('/p/getting-started');

        $response->assertOk();
        $response->assertSee('Getting Started');
        $response->assertSee('<div class="wb-dashboard-shell">', false);
        $response->assertSee('<div class="wb-sidebar-backdrop" data-wb-sidebar-backdrop></div>', false);
        $response->assertSee('data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full"', false);
        $response->assertSee('data-wb-slot="sidebar" id="docsSidebar" class="wb-sidebar"', false);
        $response->assertSee('data-wb-slot="main" id="main-content" class="wb-dashboard-main"', false);
        $response->assertSee('<header class="wb-content-header">', false);
        $response->assertSee('<h1 class="wb-content-title">Getting Started</h1>', false);
        $response->assertSee('<pre><code data-language="html">', false);
        $response->assertSee('<div class="wb-alert wb-alert-info">', false);

        $firstBlockCount = $page->blocks()->count();

        $this->artisan('project:sync-ui-docs-getting-started')->assertExitCode(0);
        $this->artisan('project:sync-ui-docs-navigation')->assertExitCode(0);

        $page->refresh();

        $this->assertSame($firstBlockCount, $page->blocks()->count());
        $this->assertSame($homeBlockCountBefore, $home->fresh()->blocks()->count());
        $this->assertSame('docs', $page->publicShellPreset());
        $this->assertSame(['docs-navbar', 'docs-main', 'docs-sidebar'], $page->slots->sortBy('sort_order')->pluck('settings.wrapper_preset')->values()->all());
        $this->assertSame(NavigationItem::MENU_DOCS, $home->fresh()->blocks()->where('type', 'sidebar-navigation')->first()?->sidebarNavigationMenuKey());
        $this->assertDatabaseHas('navigation_items', [
            'site_id' => $home->site_id,
            'menu_key' => NavigationItem::MENU_DOCS,
            'title' => 'Getting Started',
        ]);
    }

    private function createDocsHomePage(): Page
    {
        $site = Site::query()->firstOrFail();
        $headerSlotType = $this->slotType('header', 'Header', 1);
        $mainSlotType = $this->slotType('main', 'Main', 2);
        $sidebarSlotType = $this->slotType('sidebar', 'Sidebar', 3);

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
            'slot_type_id' => $headerSlotType->id,
            'sort_order' => 1,
            'settings' => ['wrapper_preset' => 'docs-navbar'],
        ]);
        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 2,
            'settings' => ['wrapper_preset' => 'docs-main'],
        ]);
        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $sidebarSlotType->id,
            'sort_order' => 3,
            'settings' => ['wrapper_preset' => 'docs-sidebar'],
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'sidebar-navigation',
            'block_type_id' => $this->blockType('sidebar-navigation')->id,
            'source_type' => 'static',
            'slot' => 'sidebar',
            'slot_type_id' => $sidebarSlotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ])->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Documentation navigation',
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $this->blockType('section')->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 0,
            'settings' => json_encode(['layout_name' => 'Home manual content'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        return $page;
    }

    private function slotType(string $slug, string $name, int $sortOrder): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => true],
        );
    }

    private function blockType(string $slug): BlockType
    {
        return BlockType::query()->where('slug', $slug)->firstOrFail();
    }
}
