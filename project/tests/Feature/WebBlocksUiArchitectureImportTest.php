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
use Project\Support\UiDocs\SetupWebBlocksUiDocsSite;
use Project\Support\UiDocs\WebBlocksUiLocalResolver;
use Tests\TestCase;

class WebBlocksUiArchitectureImportTest extends TestCase
{
    use RefreshDatabase;

    private ?string $localResolverConfigBackup = null;

    #[Test]
    public function architecture_page_import_is_idempotent_and_renders_in_docs_shell(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);
        $site = $this->createTargetSite();
        $home = $this->createDocsHomePage($site);
        $this->createGettingStartedPage($site);

        $this->artisan('project:webblocksui-import docs-architecture')->assertExitCode(0);

        $architecturePage = Page::query()
            ->with(['translations', 'slots.slotType', 'blocks.textTranslations'])
            ->where('site_id', $site->id)
            ->get()
            ->first(fn (Page $page) => $page->setting('project_page_key') === 'docs-architecture');

        $this->assertNotNull($architecturePage);
        $this->assertSame('docs', $architecturePage->publicShellPreset());
        $this->assertSame('/p/architecture', $architecturePage->publicPath());
        $this->assertSame('/docs/architecture.html', $architecturePage->setting('requested_public_path'));
        $this->assertSame('https://webblocksui.com/docs/architecture.html', $architecturePage->setting('source_url'));
        $this->assertSame(
            ['header', 'sidebar', 'main'],
            $architecturePage->slots->sortBy('sort_order')->pluck('slotType.slug')->values()->all(),
        );
        $this->assertSame(
            ['page', 'page', 'page'],
            $architecturePage->slots->sortBy('sort_order')->pluck('source_type')->values()->all(),
        );
        $this->assertSame([null, null, null], $architecturePage->slots->sortBy('sort_order')->pluck('shared_slot_id')->values()->all());

        $currentLayerModelBlock = $architecturePage->blocks
            ->first(fn (Block $block) => $block->typeSlug() === 'link-list-item' && $block->translatedTextFieldValue('title') === 'primitives + surfaces');
        $richTextBlock = $architecturePage->blocks
            ->first(fn (Block $block) => $block->typeSlug() === 'rich-text' && str_contains((string) $block->translatedTextFieldValue('content'), 'Later layers rely on earlier layers'));
        $alertBlock = $architecturePage->blocks
            ->first(fn (Block $block) => $block->typeSlug() === 'alert' && $block->translatedTextFieldValue('title') === 'Do not flatten the layer model');

        $this->assertNotNull($currentLayerModelBlock);
        $this->assertNotNull($richTextBlock);
        $this->assertNotNull($alertBlock);

        $response = $this->withHeader('Host', $site->domain)->get('/p/architecture');

        $response->assertOk();
        $response->assertSee('Architecture');
        $response->assertSee('<div class="wb-dashboard-shell">', false);
        $response->assertSee('data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full"', false);
        $response->assertSee('data-wb-slot="sidebar" id="docsSidebar" class="wb-sidebar"', false);
        $response->assertSee('data-wb-slot="main" id="main-content" class="wb-dashboard-main"', false);
        $response->assertSee('<h1 class="wb-content-title">Architecture</h1>', false);
        $response->assertSee('tokens and theme axes');
        $response->assertSee('Later layers rely on earlier layers already being present.');
        $response->assertSee('Do not flatten the layer model');
        $response->assertSee('<code>docs/</code>', false);
        $response->assertSee('/p/getting-started');
        $response->assertSee('foundation.html');

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
        $this->assertSame(
            NavigationItem::MENU_DOCS,
            $home->fresh()->blocks()->where('type', 'sidebar-navigation')->first()?->sidebarNavigationMenuKey(),
        );

        $firstPageCount = Page::query()->count();
        $firstSlotCount = PageSlot::query()->count();
        $firstBlockCount = Block::query()->count();
        $firstNavigationCount = NavigationItem::query()->count();
        $firstTranslationCount = PageTranslation::query()->count();

        $this->artisan('project:webblocksui-import docs-architecture')->assertExitCode(0);

        $this->assertSame($firstPageCount, Page::query()->count());
        $this->assertSame($firstSlotCount, PageSlot::query()->count());
        $this->assertSame($firstBlockCount, Block::query()->count());
        $this->assertSame($firstNavigationCount, NavigationItem::query()->count());
        $this->assertSame($firstTranslationCount, PageTranslation::query()->count());
        $this->assertSame(
            1,
            Page::query()->where('site_id', $site->id)->get()->filter(fn (Page $page) => $page->setting('project_page_key') === 'docs-architecture')->count(),
        );
        $this->assertSame(
            1,
            NavigationItem::query()->forSite($site->id)->forMenu(NavigationItem::MENU_DOCS)->where('title', 'Architecture')->count(),
        );
        $this->assertFalse(class_exists(\App\Console\Commands\WebBlocksUiImportCommand::class));
    }

    #[Test]
    public function import_reports_local_resolver_note_when_preview_host_is_not_configured(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);

        $this->app['env'] = 'local';
        $this->setLocalResolverConfigured(false);

        $this->artisan('project:webblocksui-setup-site')->assertExitCode(0);

        $import = $this->artisan('project:webblocksui-import docs-architecture');
        $import->expectsOutput('If the host does not resolve, run project:webblocksui-local-resolver first.');
        $import->expectsOutput('Architecture local preview URL: https://ui.docs.webblocksui.com.ddev.site/p/architecture');
        $import->assertExitCode(0);
    }

    #[Test]
    public function setup_site_uses_local_ddev_domain_and_reports_architecture_preview_url_without_duplicates(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);

        $this->app['env'] = 'local';
        $this->setLocalResolverConfigured(false);

        $first = $this->artisan('project:webblocksui-setup-site');
        $first->expectsOutput('Canonical site domain: ui.docs.webblocksui.com');
        $first->expectsOutput('Resolved local site domain: ui.docs.webblocksui.com.ddev.site');
        $first->expectsOutput('Local resolver status: not configured for ui.docs.webblocksui.com.ddev.site');
        $first->expectsOutput('If the host does not resolve, run: ddev artisan project:webblocksui-local-resolver');
        $first->expectsOutput('If the resolver command updates DDEV config, run: ddev restart');
        $first->expectsOutput('Architecture local preview URL: https://ui.docs.webblocksui.com.ddev.site/p/architecture');
        $first->assertExitCode(0);

        $this->artisan('project:webblocksui-setup-site')->assertExitCode(0);

        $site = Site::query()->where('handle', 'ui-docs-webblocksui-com')->firstOrFail();

        $this->assertSame('ui.docs.webblocksui.com.ddev.site', $site->domain);
        $this->assertSame(1, Site::query()->where('handle', 'ui-docs-webblocksui-com')->count());
        $this->assertSame(1, Page::query()->where('site_id', $site->id)->whereHas('translations', fn ($query) => $query->where('slug', 'home'))->count());
        $this->assertSame(1, Page::query()->where('site_id', $site->id)->whereHas('translations', fn ($query) => $query->where('slug', 'getting-started'))->count());
        $this->assertSame('https://ui.docs.webblocksui.com.ddev.site/p/architecture', SetupWebBlocksUiDocsSite::architecturePreviewUrl());
        $this->assertFalse(class_exists(\App\Console\Commands\SetupWebBlocksUiDocsSiteCommand::class));
    }

    protected function tearDown(): void
    {
        $this->restoreLocalResolverConfig();

        parent::tearDown();
    }

    private function setLocalResolverConfigured(bool $configured): void
    {
        $path = base_path(WebBlocksUiLocalResolver::CONFIG_PATH);

        if ($this->localResolverConfigBackup === null) {
            $this->localResolverConfigBackup = is_file($path)
                ? (string) file_get_contents($path)
                : '__MISSING__';
        }

        if (! $configured) {
            @unlink($path);

            return;
        }

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, implode(PHP_EOL, [
            '# Managed by tests',
            'additional_hostnames:',
            '  - '.SetupWebBlocksUiDocsSite::canonicalDomain(),
            '',
        ]));
    }

    private function restoreLocalResolverConfig(): void
    {
        if ($this->localResolverConfigBackup === null) {
            return;
        }

        $path = base_path(WebBlocksUiLocalResolver::CONFIG_PATH);

        if ($this->localResolverConfigBackup === '__MISSING__') {
            @unlink($path);
        } else {
            file_put_contents($path, $this->localResolverConfigBackup);
        }

        $this->localResolverConfigBackup = null;
    }

    private function createTargetSite(): Site
    {
        return Site::query()->firstOrCreate(
            ['handle' => 'ui-docs-webblocksui-com'],
            ['name' => 'WebBlocks UI Docs', 'domain' => SetupWebBlocksUiDocsSite::canonicalDomain(), 'is_primary' => true],
        );
    }

    private function createDocsHomePage(Site $site): Page
    {
        $headerSlotType = $this->slotType('header', 'Header', 1);
        $sidebarSlotType = $this->slotType('sidebar', 'Sidebar', 2);
        $mainSlotType = $this->slotType('main', 'Main', 3);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'page_type' => 'default',
            'status' => 'published',
            'settings' => ['public_shell' => 'docs'],
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'locale_id' => Page::defaultLocaleId(),
            'name' => 'Home',
            'slug' => 'home',
            'path' => '/',
        ]);

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $headerSlotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'sort_order' => 0,
        ]);
        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $sidebarSlotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'sort_order' => 1,
        ]);
        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $mainSlotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'sort_order' => 2,
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
            'settings' => json_encode(['menu_key' => NavigationItem::MENU_DOCS], JSON_UNESCAPED_SLASHES),
        ])->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Documentation navigation',
        ]);

        return $page;
    }

    private function createGettingStartedPage(Site $site): Page
    {
        $headerSlotType = $this->slotType('header', 'Header', 1);
        $sidebarSlotType = $this->slotType('sidebar', 'Sidebar', 2);
        $mainSlotType = $this->slotType('main', 'Main', 3);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'page_type' => 'default',
            'status' => 'published',
            'settings' => ['public_shell' => 'docs'],
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'locale_id' => Page::defaultLocaleId(),
            'name' => 'Getting Started',
            'slug' => 'getting-started',
            'path' => '/p/getting-started',
        ]);

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $headerSlotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'sort_order' => 0,
        ]);
        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $sidebarSlotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'sort_order' => 1,
        ]);
        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $mainSlotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'sort_order' => 2,
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
