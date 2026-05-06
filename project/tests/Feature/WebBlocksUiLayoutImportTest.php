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

class WebBlocksUiLayoutImportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function layout_page_import_is_idempotent_and_preserves_docs_shared_slots(): void
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

        $result = $importer->run('docs-layout');

        $this->assertContains('Source URL: https://webblocksui.com/docs/layout.html', $result);
        $this->assertContains('Layout local preview URL: '.SetupWebBlocksUiDocsSite::previewUrlForPath('/p/layout', $site), $result);

        $this->assertDatabaseHas('page_translations', [
            'site_id' => $site->id,
            'name' => 'Layout',
            'slug' => 'layout',
            'path' => '/p/layout',
        ]);

        $layoutPageId = PageTranslation::query()
            ->where('site_id', $site->id)
            ->where('slug', 'layout')
            ->value('page_id');

        $layoutPage = $layoutPageId
            ? Page::query()->with(['translations', 'slots.slotType', 'blocks.textTranslations'])->find($layoutPageId)
            : null;

        $this->assertNotNull($layoutPage);
        $this->assertSame('docs-layout', $layoutPage->setting('project_page_key'));
        $this->assertSame('docs', $layoutPage->publicShellPreset());
        $this->assertSame('/p/layout', $layoutPage->publicPath());
        $this->assertSame('/docs/layout.html', $layoutPage->setting('requested_public_path'));
        $this->assertSame('https://webblocksui.com/docs/layout.html', $layoutPage->setting('source_url'));

        $slots = $layoutPage->slots->sortBy('sort_order')->values();

        $this->assertSame(['header', 'sidebar', 'main'], $slots->pluck('slotType.slug')->all());
        $this->assertSame(PageSlot::SOURCE_TYPE_SHARED_SLOT, $slots[0]->source_type);
        $this->assertSame($headerSharedSlot->id, $slots[0]->shared_slot_id);
        $this->assertSame(PageSlot::SOURCE_TYPE_SHARED_SLOT, $slots[1]->source_type);
        $this->assertSame($sidebarSharedSlot->id, $slots[1]->shared_slot_id);
        $this->assertSame(PageSlot::SOURCE_TYPE_PAGE, $slots[2]->source_type);
        $this->assertNull($slots[2]->shared_slot_id);

        $anchors = $layoutPage->blocks
            ->where('type', 'header')
            ->filter(fn (Block $block) => filled($block->url))
            ->pluck('url')
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing([
            'layout',
            'base-composition-primitives',
            'simple-grid-and-flow',
            '12-column-row-system',
            'navigation-structure',
            'shell-selection',
            'header-hierarchy',
        ], $anchors);

        $this->assertTrue($layoutPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'toc'));
        $this->assertTrue($layoutPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'table' && str_contains((string) $block->content, 'wb-container* | Page width and horizontal padding')));
        $this->assertTrue($layoutPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'table' && str_contains((string) $block->content, 'Dashboard | wb-dashboard-shell')));
        $this->assertTrue($layoutPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'code' && str_contains((string) $block->content, '<div class="wb-grid-2">')));
        $this->assertTrue($layoutPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'code' && str_contains((string) $block->content, '<div class="wb-row">')));
        $this->assertTrue($layoutPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'callout' && $block->title === 'Shell rule'));
        $this->assertTrue($layoutPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'button_link' && $block->translatedTextFieldValue('title') === 'Invite'));
        $this->assertTrue($layoutPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'breadcrumb'));
        $this->assertSame(0, $layoutPage->blocks->where('slot', 'header')->count());
        $this->assertSame(0, $layoutPage->blocks->where('slot', 'sidebar')->count());

        $navigationTitles = NavigationItem::query()
            ->forSite($site->id)
            ->forMenu(NavigationItem::MENU_DOCS)
            ->orderBy('position')
            ->pluck('title')
            ->all();

        $this->assertSame(
            ['Home', 'Getting Started', 'Architecture', 'Foundation', 'Layout', 'Primitives', 'Icons', 'Patterns', 'Playground'],
            $navigationTitles,
        );
        $this->assertSame(1, NavigationItem::query()->forSite($site->id)->forMenu(NavigationItem::MENU_DOCS)->where('title', 'Layout')->count());
        $this->assertSame(
            NavigationItem::MENU_DOCS,
            $home->fresh()->blocks()->where('type', 'sidebar-navigation')->first()?->sidebarNavigationMenuKey(),
        );

        $firstPageCount = Page::query()->count();
        $firstSlotCount = PageSlot::query()->count();
        $firstBlockCount = Block::query()->count();
        $firstNavigationCount = NavigationItem::query()->count();
        $firstTranslationCount = PageTranslation::query()->count();

        $rerun = $this->artisan('project:webblocksui-import docs-layout');
        $rerun->expectsOutput('Source URL: https://webblocksui.com/docs/layout.html');
        $rerun->expectsOutput('Layout local preview URL: '.SetupWebBlocksUiDocsSite::previewUrlForPath('/p/layout', $site));
        $rerun->assertExitCode(0);

        $this->assertSame($firstPageCount, Page::query()->count());
        $this->assertSame($firstSlotCount, PageSlot::query()->count());
        $this->assertSame($firstBlockCount, Block::query()->count());
        $this->assertSame($firstNavigationCount, NavigationItem::query()->count());
        $this->assertSame($firstTranslationCount, PageTranslation::query()->count());
        $this->assertSame(
            1,
            Page::query()->where('site_id', $site->id)->get()->filter(fn (Page $page) => $page->setting('project_page_key') === 'docs-layout')->count(),
        );
    }

    #[Test]
    public function imported_layout_page_toc_renders_entries_from_explicit_header_anchors(): void
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

        $this->artisan('project:webblocksui-import docs-layout')->assertExitCode(0);

        $response = $this->get('/p/layout');

        $response->assertOk();
        $response->assertSee('On this page');
        $response->assertDontSee('href="#layout"', false);
        $response->assertSee('href="#base-composition-primitives"', false);
        $response->assertSee('href="#simple-grid-and-flow"', false);
        $response->assertSee('href="#12-column-row-system"', false);
        $response->assertSee('href="#navigation-structure"', false);
        $response->assertSee('href="#shell-selection"', false);
        $response->assertSee('href="#header-hierarchy"', false);
        $response->assertDontSee('href="#stack-cluster-split"', false);
        $response->assertSee('Layout is where page width, structural flow, navigation frame, and shell choice live.');
        $response->assertSee('Use navbar for top-level movement and actions.');
        $response->assertSee('Manage people, roles, and invitations.');
        $response->assertSee('Invite');
        $response->assertSee('<a class="wb-breadcrumb-link" href="/">Home</a>', false);
        $response->assertSee('<span class="wb-breadcrumb-current" aria-current="page">Layout</span>', false);
    }
}
