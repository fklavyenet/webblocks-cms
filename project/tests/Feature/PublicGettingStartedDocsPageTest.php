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
        BlockType::query()->where('slug', 'code')->update(['status' => 'published']);

        $home = $this->createDocsHomePage();
        $page = $this->createGettingStartedPage($home->site_id);
        $homeBlockCountBefore = $home->blocks()->count();

        $this->artisan('webblocks:sync-ui-docs-getting-started')->assertExitCode(0);
        $this->artisan('project:sync-ui-docs-navigation')->assertExitCode(0);

        $page->refresh();

        $response = $this->get('/p/getting-started');
        $html = $response->getContent();

        $response->assertOk();
        $response->assertSee('Getting Started');
        $response->assertSee('<div class="wb-dashboard-shell">', false);
        $response->assertSee('<div class="wb-sidebar-backdrop" data-wb-sidebar-backdrop></div>', false);
        $response->assertSee('<div class="wb-dashboard-body wb-w-full">', false);
        $response->assertSee('data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full"', false);
        $response->assertSee('data-wb-slot="sidebar" id="docsSidebar" class="wb-sidebar"', false);
        $response->assertSee('data-wb-slot="main" id="main-content" class="wb-dashboard-main"', false);
        $response->assertSeeInOrder([
            'data-wb-slot="sidebar" id="docsSidebar" class="wb-sidebar"',
            '<div class="wb-dashboard-body wb-w-full">',
            'data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full"',
            'data-wb-slot="main" id="main-content" class="wb-dashboard-main"',
        ], false);
        $response->assertSee('<header class="wb-content-header">', false);
        $response->assertSee('<h1 class="wb-content-title">Getting Started</h1>', false);
        $response->assertSee('<pre><code data-language="html">', false);
        $response->assertDontSee('wb-card wb-card-muted', false);
        $response->assertDontSee('<div class="wb-text-sm wb-text-muted">html</div>', false);
        $response->assertDontSee('>html<', false);
        $response->assertSee('<div class="wb-alert wb-alert-info">', false);
        $response->assertSee('Copy the nearest shipped example');
        $response->assertDontSee('wb-navbar-spacer', false);
        $this->assertMatchesRegularExpression('/<div class="wb-dashboard-shell">\s*<aside\b[^>]*data-wb-slot="sidebar"[^>]*>.*?<\/aside>\s*<div class="wb-dashboard-body wb-w-full">\s*<nav\b[^>]*data-wb-slot="header"[^>]*>.*?<main\b[^>]*data-wb-slot="main"[^>]*>/s', $html);
        $this->assertDoesNotMatchRegularExpression('/<div class="wb-dashboard-shell">\s*<aside\b[^>]*data-wb-slot="sidebar"[^>]*>.*?<\/aside>\s*<header\b[^>]*data-wb-slot="header"[^>]*>/s', $html);

        $firstBlockCount = $page->blocks()->count();

        $this->artisan('webblocks:sync-ui-docs-getting-started')->assertExitCode(0);
        $this->artisan('project:sync-ui-docs-navigation')->assertExitCode(0);

        $page->refresh();

        $this->assertSame($firstBlockCount, $page->blocks()->count());
        $this->assertSame($homeBlockCountBefore, $home->fresh()->blocks()->count());
        $this->assertSame('docs', $page->publicShellPreset());
        $this->assertSame([null, null, null], $page->slots->sortBy('sort_order')->pluck('settings')->values()->all());
        $this->assertSame(NavigationItem::MENU_DOCS, $home->fresh()->blocks()->where('type', 'sidebar-navigation')->first()?->sidebarNavigationMenuKey());
        $this->assertDatabaseHas('navigation_items', [
            'site_id' => $home->site_id,
            'menu_key' => NavigationItem::MENU_DOCS,
            'title' => 'Getting Started',
        ]);
        $this->assertDatabaseCount('page_translations', 2);
        $this->assertDatabaseMissing('page_translations', [
            'page_id' => $page->id,
            'locale_id' => 2,
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
        ]);
        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 2,
        ]);
        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $sidebarSlotType->id,
            'sort_order' => 3,
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

    private function createGettingStartedPage(int $siteId): Page
    {
        $headerSlotType = $this->slotType('header', 'Header', 1);
        $sidebarSlotType = $this->slotType('sidebar', 'Sidebar', 2);
        $mainSlotType = $this->slotType('main', 'Main', 3);

        $page = Page::query()->create([
            'site_id' => $siteId,
            'page_type' => 'default',
            'status' => 'published',
            'settings' => ['public_shell' => 'docs'],
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'site_id' => $siteId,
            'locale_id' => Page::defaultLocaleId(),
            'name' => 'Getting Started',
            'slug' => 'getting-started',
            'path' => '/p/getting-started',
        ]);

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $headerSlotType->id,
            'sort_order' => 0,
            'settings' => null,
        ]);

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $sidebarSlotType->id,
            'sort_order' => 1,
            'settings' => null,
        ]);

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 2,
            'settings' => null,
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
