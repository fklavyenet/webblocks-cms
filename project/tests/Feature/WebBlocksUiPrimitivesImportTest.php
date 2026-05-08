<?php

namespace Project\Tests\Feature;

use App\Models\Block;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\SharedSlot;
use App\Models\Site;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Project\Support\UiDocs\SetupWebBlocksUiDocsSite;
use Project\Support\UiDocs\WebBlocksUiImporter;
use Tests\TestCase;

class WebBlocksUiPrimitivesImportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function primitives_payload_exists_and_is_registered_in_the_manifest(): void
    {
        $manifestPath = base_path('storage/project/webblocksui.com/manifest.json');
        $payloadPath = base_path('storage/project/webblocksui.com/docs-primitives.json');

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $payload = json_decode((string) file_get_contents($payloadPath), true);

        $this->assertIsArray($manifest);
        $this->assertIsArray($payload);
        $this->assertSame('docs-primitives.json', $manifest['payloads']['docs-primitives']['file'] ?? null);
        $this->assertSame('https://webblocksui.com/docs/primitives.html', $manifest['payloads']['docs-primitives']['source_url'] ?? null);
        $this->assertSame('/docs/primitives.html', $manifest['payloads']['docs-primitives']['requested_public_path'] ?? null);
        $this->assertSame('default_site', $payload['site']['target'] ?? null);
        $this->assertSame('docs-primitives', $payload['page']['key'] ?? null);
    }

    #[Test]
    public function primitives_page_import_is_idempotent_and_preserves_docs_shared_slots(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);

        $this->artisan('project:webblocksui-setup-site')->assertExitCode(0);

        $site = Site::query()->where('handle', 'default')->firstOrFail();
        $home = Page::query()
            ->where('site_id', $site->id)
            ->whereHas('translations', fn ($query) => $query->where('slug', 'home'))
            ->firstOrFail();

        $headerSharedSlot = SharedSlot::query()->create([
            'site_id' => $site->id,
            'name' => 'Docs Header',
            'handle' => 'docs-header',
            'slot_name' => 'header',
            'public_shell' => 'docs',
            'is_active' => true,
        ]);
        $sidebarSharedSlot = SharedSlot::query()->create([
            'site_id' => $site->id,
            'name' => 'Docs Sidebar',
            'handle' => 'docs-sidebar',
            'slot_name' => 'sidebar',
            'public_shell' => 'docs',
            'is_active' => true,
        ]);

        $importer = $this->app->make(WebBlocksUiImporter::class);
        $importer->run('docs-architecture');
        $importer->run('docs-foundation');
        $importer->run('docs-layout');

        $result = $importer->run('docs-primitives');

        $this->assertContains('Source URL: https://webblocksui.com/docs/primitives.html', $result);
        $this->assertContains('Primitives local preview URL: '.SetupWebBlocksUiDocsSite::previewUrlForPath('/p/primitives', $site), $result);

        $this->assertDatabaseHas('page_translations', [
            'site_id' => $site->id,
            'name' => 'Primitives',
            'slug' => 'primitives',
            'path' => '/p/primitives',
        ]);

        $primitivesPageId = PageTranslation::query()
            ->where('site_id', $site->id)
            ->where('slug', 'primitives')
            ->value('page_id');

        $primitivesPage = $primitivesPageId
            ? Page::query()->with(['translations', 'slots.slotType', 'blocks.textTranslations'])->find($primitivesPageId)
            : null;

        $this->assertNotNull($primitivesPage);
        $this->assertSame('docs-primitives', $primitivesPage->setting('project_page_key'));
        $this->assertSame('docs', $primitivesPage->publicShellPreset());
        $this->assertSame('/p/primitives', $primitivesPage->publicPath());
        $this->assertSame('/docs/primitives.html', $primitivesPage->setting('requested_public_path'));
        $this->assertSame('/p/primitives', $primitivesPage->setting('current_public_path'));
        $this->assertSame('https://webblocksui.com/docs/primitives.html', $primitivesPage->setting('source_url'));

        $slots = $primitivesPage->slots->sortBy('sort_order')->values();

        $this->assertSame(['header', 'sidebar', 'main'], $slots->pluck('slotType.slug')->all());
        $this->assertSame(PageSlot::SOURCE_TYPE_SHARED_SLOT, $slots[0]->source_type);
        $this->assertSame($headerSharedSlot->id, $slots[0]->shared_slot_id);
        $this->assertSame(PageSlot::SOURCE_TYPE_SHARED_SLOT, $slots[1]->source_type);
        $this->assertSame($sidebarSharedSlot->id, $slots[1]->shared_slot_id);
        $this->assertSame(PageSlot::SOURCE_TYPE_PAGE, $slots[2]->source_type);
        $this->assertNull($slots[2]->shared_slot_id);

        $anchors = $primitivesPage->blocks
            ->where('type', 'header')
            ->filter(fn (Block $block) => filled($block->url))
            ->pluck('url')
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing([
            'primitives',
            'primitive-boundaries',
            'actions-and-signals',
            'forms',
            'surfaces-and-display-contracts',
            'editorial-body-copy',
            'pagination-and-context-navigation',
            'interaction-shells',
            'primitive-checklist',
            'stats',
            'alerts',
            'readable-body-copy',
            'rhythm-variants',
            'default-rhythm',
            'compact-table-footer',
            'dropdown-popover-and-tabs',
            'accordion-and-drawer-flows',
            'modal-dialog-and-media-viewer',
            'primitive-modal',
        ], $anchors);

        $this->assertTrue($primitivesPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'toc'));
        $this->assertTrue($primitivesPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'callout' && $block->title === 'Icon boundary'));
        $this->assertTrue($primitivesPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'table' && str_contains((string) $block->content, 'Primary actions and status')));
        $this->assertTrue($primitivesPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'table' && str_contains((string) $block->content, 'Project name | Hint: Use a short internal name.')));
        $this->assertTrue($primitivesPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'code' && str_contains((string) $block->content, '<div class="wb-alert wb-alert-info">')));
        $this->assertTrue($primitivesPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'code' && str_contains((string) $block->content, 'data-wb-tab="panel-id"')));
        $this->assertTrue($primitivesPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'accordion' && $block->title === 'Primitive questions'));
        $this->assertTrue($primitivesPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'stat-card' && $block->translatedTextFieldValue('subtitle') === 'MRR'));
        $this->assertTrue($primitivesPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'button_link' && $block->translatedTextFieldValue('title') === 'Open Playground'));
        $this->assertSame(0, $primitivesPage->blocks->where('slot', 'header')->count());
        $this->assertSame(0, $primitivesPage->blocks->where('slot', 'sidebar')->count());

        $navigationTitles = NavigationItem::query()
            ->forSite($site->id)
            ->forMenu(NavigationItem::MENU_DOCS)
            ->whereNull('parent_id')
            ->orderBy('position')
            ->pluck('title')
            ->all();

        $this->assertSame([
            'Home',
            'Getting Started',
            'Architecture',
            'Foundation',
            'Layout',
            'Primitives',
            'Icons',
            'Patterns',
            'Utilities',
            'JavaScript',
            'Playground',
        ], $navigationTitles);

        $this->assertSame(1, NavigationItem::query()->forSite($site->id)->forMenu(NavigationItem::MENU_DOCS)->where('title', 'Primitives')->count());
        $patternsGroup = NavigationItem::query()->forSite($site->id)->forMenu(NavigationItem::MENU_DOCS)->where('title', 'Patterns')->first();
        $this->assertNotNull($patternsGroup);
        $this->assertSame(NavigationItem::LINK_GROUP, $patternsGroup->link_type);
        $this->assertSame(8, $patternsGroup->position);
        $this->assertSame(9, NavigationItem::query()->forSite($site->id)->forMenu(NavigationItem::MENU_DOCS)->where('parent_id', $patternsGroup->id)->count());
        $this->assertSame(1, NavigationItem::query()->forSite($site->id)->forMenu(NavigationItem::MENU_DOCS)->where('parent_id', $patternsGroup->id)->where('title', 'Overview')->count());
        $this->assertSame(1, NavigationItem::query()->forSite($site->id)->forMenu(NavigationItem::MENU_DOCS)->where('parent_id', $patternsGroup->id)->where('title', 'Dashboard Shell')->count());
        $this->assertSame(0, NavigationItem::query()->forSite($site->id)->forMenu(NavigationItem::MENU_DOCS)->where('title', 'Patterns / Overview')->count());
        $homeResponse = $this->get('/');
        $homeResponse->assertOk();
        $homeResponse->assertSee('data-wb-nav-group-toggle', false);
        $homeResponse->assertSee('aria-controls="wb-nav-group-items-', false);
        $homeResponse->assertSee('>Patterns<', false);
        $homeResponse->assertSee('>Overview<', false);
        $homeResponse->assertSee('>Dashboard Shell<', false);
        $homeResponse->assertSee('assets/webblocks-cms/js/public/sidebar-navigation.js', false);
        $homeResponse->assertSee('data-wb-slot="sidebar" id="docsSidebar" class="wb-sidebar"', false);
        $this->assertSame(
            NavigationItem::MENU_DOCS,
            $home->fresh()->blocks()->where('type', 'sidebar-navigation')->first()?->sidebarNavigationMenuKey(),
        );

        $primitivesNavItem = NavigationItem::query()
            ->forSite($site->id)
            ->forMenu(NavigationItem::MENU_DOCS)
            ->where('title', 'Primitives')
            ->firstOrFail();

        $this->assertSame(NavigationItem::LINK_PAGE, $primitivesNavItem->link_type);
        $this->assertSame($primitivesPage->id, $primitivesNavItem->page_id);

        $firstPageCount = Page::query()->count();
        $firstSlotCount = PageSlot::query()->count();
        $firstBlockCount = Block::query()->count();
        $firstNavigationCount = NavigationItem::query()->count();
        $firstTranslationCount = PageTranslation::query()->count();

        $rerun = $this->artisan('project:webblocksui-import docs-primitives');
        $rerun->expectsOutput('Source URL: https://webblocksui.com/docs/primitives.html');
        $rerun->expectsOutput('Primitives local preview URL: '.SetupWebBlocksUiDocsSite::previewUrlForPath('/p/primitives', $site));
        $rerun->assertExitCode(0);

        $this->assertSame($firstPageCount, Page::query()->count());
        $this->assertSame($firstSlotCount, PageSlot::query()->count());
        $this->assertSame($firstBlockCount, Block::query()->count());
        $this->assertSame($firstNavigationCount, NavigationItem::query()->count());
        $this->assertSame($firstTranslationCount, PageTranslation::query()->count());
        $this->assertSame(
            1,
            Page::query()->where('site_id', $site->id)->get()->filter(fn (Page $page) => $page->setting('project_page_key') === 'docs-primitives')->count(),
        );

        $slotsAfterRerun = $primitivesPage->fresh(['slots.slotType'])->slots->sortBy('sort_order')->values();
        $this->assertSame(PageSlot::SOURCE_TYPE_SHARED_SLOT, $slotsAfterRerun[0]->source_type);
        $this->assertSame($headerSharedSlot->id, $slotsAfterRerun[0]->shared_slot_id);
        $this->assertSame(PageSlot::SOURCE_TYPE_SHARED_SLOT, $slotsAfterRerun[1]->source_type);
        $this->assertSame($sidebarSharedSlot->id, $slotsAfterRerun[1]->shared_slot_id);
        $this->assertSame(PageSlot::SOURCE_TYPE_PAGE, $slotsAfterRerun[2]->source_type);
        $this->assertNull($slotsAfterRerun[2]->shared_slot_id);
    }

    #[Test]
    public function imported_primitives_page_toc_renders_entries_from_explicit_header_anchors(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);

        $this->artisan('project:webblocksui-setup-site')->assertExitCode(0);

        $site = Site::query()->where('handle', 'default')->firstOrFail();

        SharedSlot::query()->create([
            'site_id' => $site->id,
            'name' => 'Docs Header',
            'handle' => 'docs-header',
            'slot_name' => 'header',
            'public_shell' => 'docs',
            'is_active' => true,
        ]);
        SharedSlot::query()->create([
            'site_id' => $site->id,
            'name' => 'Docs Sidebar',
            'handle' => 'docs-sidebar',
            'slot_name' => 'sidebar',
            'public_shell' => 'docs',
            'is_active' => true,
        ]);

        $this->artisan('project:webblocksui-import docs-architecture')->assertExitCode(0);
        $this->artisan('project:webblocksui-import docs-foundation')->assertExitCode(0);
        $this->artisan('project:webblocksui-import docs-layout')->assertExitCode(0);
        $this->artisan('project:webblocksui-import docs-primitives')->assertExitCode(0);

        $response = $this->get('/p/primitives');

        $response->assertOk();
        $response->assertSee('href="#primitive-boundaries"', false);
        $response->assertSee('href="#actions-and-signals"', false);
        $response->assertSee('href="#stats"', false);
        $response->assertSee('href="#alerts"', false);
        $response->assertSee('href="#readable-body-copy"', false);
        $response->assertSee('href="#dropdown-popover-and-tabs"', false);
        $response->assertSee('href="#primitive-modal"', false);
        $response->assertDontSee('href="#primitives"', false);
    }
}
