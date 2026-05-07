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

class WebBlocksUiIconsImportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function icons_payload_exists_and_is_registered_in_the_manifest(): void
    {
        $manifestPath = base_path('storage/project/webblocksui.com/manifest.json');
        $payloadPath = base_path('storage/project/webblocksui.com/docs-icons.json');

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $payload = json_decode((string) file_get_contents($payloadPath), true);

        $this->assertIsArray($manifest);
        $this->assertIsArray($payload);
        $this->assertSame('docs-icons.json', $manifest['payloads']['docs-icons']['file'] ?? null);
        $this->assertSame('https://webblocksui.com/docs/icons.html', $manifest['payloads']['docs-icons']['source_url'] ?? null);
        $this->assertSame('/docs/icons.html', $manifest['payloads']['docs-icons']['requested_public_path'] ?? null);
        $this->assertSame('default_site', $payload['site']['target'] ?? null);
        $this->assertSame('docs-icons', $payload['page']['key'] ?? null);
    }

    #[Test]
    public function icons_page_import_is_idempotent_and_preserves_docs_shared_slots(): void
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
        $importer->run('docs-primitives');

        $result = $importer->run('docs-icons');

        $this->assertContains('Source URL: https://webblocksui.com/docs/icons.html', $result);
        $this->assertContains('Icons local preview URL: '.SetupWebBlocksUiDocsSite::previewUrlForPath('/p/icons', $site), $result);

        $this->assertDatabaseHas('page_translations', [
            'site_id' => $site->id,
            'name' => 'Icons',
            'slug' => 'icons',
            'path' => '/p/icons',
        ]);

        $iconsPageId = PageTranslation::query()
            ->where('site_id', $site->id)
            ->where('slug', 'icons')
            ->value('page_id');

        $iconsPage = $iconsPageId
            ? Page::query()->with(['translations', 'slots.slotType', 'blocks.textTranslations'])->find($iconsPageId)
            : null;

        $this->assertNotNull($iconsPage);
        $this->assertSame('docs-icons', $iconsPage->setting('project_page_key'));
        $this->assertSame('docs', $iconsPage->publicShellPreset());
        $this->assertSame('/p/icons', $iconsPage->publicPath());
        $this->assertSame('/docs/icons.html', $iconsPage->setting('requested_public_path'));
        $this->assertSame('/p/icons', $iconsPage->setting('current_public_path'));
        $this->assertSame('https://webblocksui.com/docs/icons.html', $iconsPage->setting('source_url'));

        $slots = $iconsPage->slots->sortBy('sort_order')->values();

        $this->assertSame(['header', 'sidebar', 'main'], $slots->pluck('slotType.slug')->all());
        $this->assertSame(PageSlot::SOURCE_TYPE_SHARED_SLOT, $slots[0]->source_type);
        $this->assertSame($headerSharedSlot->id, $slots[0]->shared_slot_id);
        $this->assertSame(PageSlot::SOURCE_TYPE_SHARED_SLOT, $slots[1]->source_type);
        $this->assertSame($sidebarSharedSlot->id, $slots[1]->shared_slot_id);
        $this->assertSame(PageSlot::SOURCE_TYPE_PAGE, $slots[2]->source_type);
        $this->assertNull($slots[2]->shared_slot_id);

        $anchors = $iconsPage->blocks
            ->where('type', 'header')
            ->filter(fn (Block $block) => filled($block->url))
            ->pluck('url')
            ->values()
            ->all();

        $this->assertSame([
            'icons',
            'mask-image-reference',
            'basic-usage',
            'important-notes',
            'all-shipped-icon-classes',
        ], $anchors);

        $this->assertTrue($iconsPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'toc'));
        $this->assertTrue($iconsPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'stat-card' && $block->translatedTextFieldValue('subtitle') === 'Valid icon classes'));
        $this->assertTrue($iconsPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'callout' && (($block->translatedTextFieldValue('title') ?? $block->title) === 'Aliases ship as real classes')));
        $this->assertTrue($iconsPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'callout' && (($block->translatedTextFieldValue('title') ?? $block->title) === 'Missing classes stay visible')));

        $basicUsageCode = $iconsPage->blocks
            ->first(fn (Block $block) => $block->typeSlug() === 'code' && str_contains((string) $block->content, 'webblocks-icons.css'));
        $rawHtmlBlob = $iconsPage->blocks
            ->first(fn (Block $block) => $block->typeSlug() === 'html' && str_contains((string) $block->content, 'wb-icon-home'));

        $this->assertNotNull($basicUsageCode);
        $this->assertSame('html', $basicUsageCode->setting('language'));
        $this->assertNull($rawHtmlBlob);

        $searchableContent = $iconsPage->blocks
            ->map(fn (Block $block) => implode("\n", array_filter([
                (string) ($block->translatedTextFieldValue('title') ?? $block->title),
                (string) ($block->translatedTextFieldValue('subtitle') ?? $block->subtitle),
                (string) ($block->translatedTextFieldValue('content') ?? $block->content),
                (string) ($block->translatedTextFieldValue('meta') ?? $block->meta),
            ])))
            ->implode("\n");

        $this->assertStringContainsString('wb-icon-refresh', $searchableContent);
        $this->assertStringContainsString('wb-icon-refresh-cw', $searchableContent);
        $this->assertStringContainsString('wb-icon-rotate-cw', $searchableContent);
        $this->assertStringContainsString('wb-icon-sync', $searchableContent);
        $this->assertStringContainsString('wb-icon-repeat', $searchableContent);
        $this->assertStringContainsString('wb-icon-*', $searchableContent);
        $this->assertStringContainsString('help-circle', $searchableContent);
        $this->assertStringContainsString('activity | wb-icon-activity', $searchableContent);
        $this->assertStringContainsString('home | wb-icon-home', $searchableContent);
        $this->assertStringContainsString('settings | wb-icon-settings', $searchableContent);
        $this->assertStringContainsString('refresh | wb-icon-refresh', $searchableContent);
        $this->assertStringContainsString('refresh-cw | wb-icon-refresh-cw', $searchableContent);
        $this->assertStringContainsString('rotate-cw | wb-icon-rotate-cw', $searchableContent);
        $this->assertStringContainsString('sync | wb-icon-sync', $searchableContent);
        $this->assertStringContainsString('repeat | wb-icon-repeat', $searchableContent);
        $this->assertStringContainsString('youtube | wb-icon-youtube', $searchableContent);

        $this->assertSame(0, $iconsPage->blocks->where('slot', 'header')->count());
        $this->assertSame(0, $iconsPage->blocks->where('slot', 'sidebar')->count());

        $navigationTitles = NavigationItem::query()
            ->forSite($site->id)
            ->forMenu(NavigationItem::MENU_DOCS)
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
            'Patterns / Overview',
            'Dashboard Shell',
            'Settings Shell',
            'Auth Shell',
            'Content Shell',
            'Breadcrumb',
            'Gallery',
            'Cookie Consent',
            'Marketing',
            'Utilities',
            'JavaScript',
            'Playground',
        ], $navigationTitles);

        $this->assertSame(
            NavigationItem::MENU_DOCS,
            $home->fresh()->blocks()->where('type', 'sidebar-navigation')->first()?->sidebarNavigationMenuKey(),
        );

        $iconsNavItem = NavigationItem::query()
            ->forSite($site->id)
            ->forMenu(NavigationItem::MENU_DOCS)
            ->where('title', 'Icons')
            ->firstOrFail();

        $this->assertSame(NavigationItem::LINK_PAGE, $iconsNavItem->link_type);
        $this->assertSame($iconsPage->id, $iconsNavItem->page_id);

        $firstPageCount = Page::query()->count();
        $firstSlotCount = PageSlot::query()->count();
        $firstBlockCount = Block::query()->count();
        $firstNavigationCount = NavigationItem::query()->count();
        $firstTranslationCount = PageTranslation::query()->count();

        $rerun = $this->artisan('project:webblocksui-import docs-icons');
        $rerun->expectsOutput('Source URL: https://webblocksui.com/docs/icons.html');
        $rerun->expectsOutput('Icons local preview URL: '.SetupWebBlocksUiDocsSite::previewUrlForPath('/p/icons', $site));
        $rerun->assertExitCode(0);

        $this->assertSame($firstPageCount, Page::query()->count());
        $this->assertSame($firstSlotCount, PageSlot::query()->count());
        $this->assertSame($firstBlockCount, Block::query()->count());
        $this->assertSame($firstNavigationCount, NavigationItem::query()->count());
        $this->assertSame($firstTranslationCount, PageTranslation::query()->count());
        $this->assertSame(
            1,
            Page::query()->where('site_id', $site->id)->get()->filter(fn (Page $page) => $page->setting('project_page_key') === 'docs-icons')->count(),
        );
        $this->assertSame(
            1,
            NavigationItem::query()->forSite($site->id)->forMenu(NavigationItem::MENU_DOCS)->where('title', 'Icons')->count(),
        );

        $slotsAfterRerun = $iconsPage->fresh(['slots.slotType'])->slots->sortBy('sort_order')->values();
        $this->assertSame(PageSlot::SOURCE_TYPE_SHARED_SLOT, $slotsAfterRerun[0]->source_type);
        $this->assertSame($headerSharedSlot->id, $slotsAfterRerun[0]->shared_slot_id);
        $this->assertSame(PageSlot::SOURCE_TYPE_SHARED_SLOT, $slotsAfterRerun[1]->source_type);
        $this->assertSame($sidebarSharedSlot->id, $slotsAfterRerun[1]->shared_slot_id);
        $this->assertSame(PageSlot::SOURCE_TYPE_PAGE, $slotsAfterRerun[2]->source_type);
        $this->assertNull($slotsAfterRerun[2]->shared_slot_id);
    }

    #[Test]
    public function imported_icons_page_toc_renders_entries_from_explicit_header_anchors(): void
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
        $this->artisan('project:webblocksui-import docs-icons')->assertExitCode(0);

        $response = $this->get('/p/icons');

        $response->assertOk();
        $response->assertSee('On this page');
        $response->assertSee('href="#mask-image-reference"', false);
        $response->assertSee('href="#basic-usage"', false);
        $response->assertSee('href="#important-notes"', false);
        $response->assertSee('href="#all-shipped-icon-classes"', false);
        $response->assertDontSee('href="#icons"', false);
    }
}
