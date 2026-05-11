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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebBlocksUiRemainingHtmlImportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function remaining_docs_html_import_creates_missing_pages_and_preserves_curated_pages(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);

        $this->artisan('project:webblocksui-setup-site')->assertExitCode(0);

        $site = Site::query()->where('handle', 'default')->firstOrFail();

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

        $this->artisan('project:webblocksui-import docs-architecture')->assertExitCode(0);
        $this->artisan('project:webblocksui-import docs-foundation')->assertExitCode(0);
        $this->artisan('project:webblocksui-import docs-layout')->assertExitCode(0);
        $this->artisan('project:webblocksui-import docs-primitives')->assertExitCode(0);
        $this->artisan('project:webblocksui-import docs-icons')->assertExitCode(0);

        $curatedBlockCount = Page::query()
            ->where('site_id', $site->id)
            ->get()
            ->filter(fn (Page $page) => in_array($page->setting('project_page_key'), ['docs-architecture', 'docs-foundation', 'docs-layout', 'docs-primitives', 'docs-icons'], true))
            ->mapWithKeys(fn (Page $page) => [(string) $page->setting('project_page_key') => $page->blocks()->count()])
            ->all();

        Http::fake([
            'https://ui.webblocksui.com/docs/patterns.html' => Http::response($this->patternsHtml(), 200),
            'https://ui.webblocksui.com/docs/pattern-dashboard-shell.html' => Http::response($this->genericDocHtml('Dashboard Shell', 'pattern-dashboard-shell', 'Patterns', 'Dashboard Shell', 'pattern-settings-shell.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-settings-shell.html' => Http::response($this->genericDocHtml('Settings Shell', 'pattern-settings-shell', 'Patterns', 'Settings Shell', 'pattern-admin-standards.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-admin-standards.html' => Http::response($this->genericDocHtml('Admin Standards', 'pattern-admin-standards', 'Patterns', 'Admin Standards', 'pattern-auth-shell.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-auth-shell.html' => Http::response($this->genericDocHtml('Auth Shell', 'pattern-auth-shell', 'Patterns', 'Auth Shell', 'pattern-content-shell.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-content-shell.html' => Http::response($this->genericDocHtml('Content Shell', 'pattern-content-shell', 'Patterns', 'Content Shell', 'pattern-breadcrumb.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-breadcrumb.html' => Http::response($this->genericDocHtml('Breadcrumb', 'pattern-breadcrumb', 'Patterns', 'Breadcrumb', 'pattern-gallery.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-gallery.html' => Http::response($this->genericDocHtml('Gallery', 'pattern-gallery', 'Patterns', 'Gallery', 'pattern-cookie-consent.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-cookie-consent.html' => Http::response($this->genericDocHtml('Cookie Consent', 'pattern-cookie-consent', 'Patterns', 'Cookie Consent', 'pattern-marketing.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-marketing.html' => Http::response($this->genericDocHtml('Marketing', 'pattern-marketing', 'Patterns', 'Marketing', 'utilities.html'), 200),
            'https://ui.webblocksui.com/docs/utilities.html' => Http::response($this->genericDocHtml('Utilities', 'utilities', 'WebBlocks UI', 'Utilities', 'javascript.html'), 200),
            'https://ui.webblocksui.com/docs/javascript.html' => Http::response($this->javascriptHtml(), 200),
        ]);

        $exitCode = Artisan::call('project:webblocksui-import', ['key' => 'remaining-docs-html']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('Imported payload key: docs-patterns', $output);
        $this->assertStringContainsString('Remaining HTML docs created: 12', $output);
        $this->assertStringContainsString('Remaining HTML docs updated: 0', $output);
        $this->assertStringContainsString('Remaining HTML docs skipped: 0', $output);
        $this->assertStringContainsString('Remaining HTML docs manifest pages: 12', $output);

        $this->assertDatabaseHas('pages', [
            'site_id' => $site->id,
        ]);
        $this->assertDatabaseHas('page_translations', [
            'site_id' => $site->id,
            'slug' => 'patterns',
            'path' => '/p/patterns',
        ]);

        $patternsPageId = PageTranslation::query()
            ->where('site_id', $site->id)
            ->where('slug', 'patterns')
            ->value('page_id');

        $patternsPage = $patternsPageId
            ? Page::query()->with(['translations', 'slots.slotType', 'blocks.textTranslations'])->find($patternsPageId)
            : null;

        $this->assertNotNull($patternsPage);
        $this->assertSame('docs-patterns', $patternsPage->setting('project_page_key'));
        $this->assertSame('docs', $patternsPage->publicShellPreset());
        $this->assertSame('/p/patterns', $patternsPage->publicPath());
        $this->assertSame('/docs/patterns.html', $patternsPage->setting('requested_public_path'));
        $this->assertSame('https://ui.webblocksui.com/docs/patterns.html', $patternsPage->setting('source_url'));
        $this->assertSame('trusted-main-html', $patternsPage->setting('import_strategy'));

        $slots = $patternsPage->slots->sortBy('sort_order')->values();
        $this->assertSame(['header', 'sidebar', 'main'], $slots->pluck('slotType.slug')->all());
        $this->assertSame(PageSlot::SOURCE_TYPE_SHARED_SLOT, $slots[0]->source_type);
        $this->assertSame($headerSharedSlot->id, $slots[0]->shared_slot_id);
        $this->assertSame(PageSlot::SOURCE_TYPE_SHARED_SLOT, $slots[1]->source_type);
        $this->assertSame($sidebarSharedSlot->id, $slots[1]->shared_slot_id);
        $this->assertSame(PageSlot::SOURCE_TYPE_PAGE, $slots[2]->source_type);
        $this->assertNull($slots[2]->shared_slot_id);

        $mainBlocks = $patternsPage->blocks->where('slot', 'main')->values();
        $this->assertCount(1, $mainBlocks);
        $this->assertSame('html', $mainBlocks[0]->type);
        $this->assertSame('Imported static main HTML', $mainBlocks[0]->setting('label'));
        $this->assertStringContainsString('class="wb-content-title">Patterns</h1>', (string) $mainBlocks[0]->content);
        $this->assertStringContainsString('href="/p/utilities"', (string) $mainBlocks[0]->content);
        $this->assertStringContainsString('href="/p/pattern-admin-standards"', (string) $mainBlocks[0]->content);
        $this->assertStringContainsString('src="https://ui.webblocksui.com/assets/example.jpg"', (string) $mainBlocks[0]->content);
        $this->assertStringNotContainsString('wb-sidebar', (string) $mainBlocks[0]->content);
        $this->assertStringNotContainsString('wb-navbar', (string) $mainBlocks[0]->content);
        $this->assertStringNotContainsString('wb-docs-breadcrumb', (string) $mainBlocks[0]->content);
        $this->assertStringNotContainsString('<script', (string) $mainBlocks[0]->content);
        $this->assertStringContainsString('wb-overlay-root', (string) $mainBlocks[0]->content);

        foreach ($curatedBlockCount as $key => $count) {
            $page = Page::query()->where('site_id', $site->id)->get()->first(fn (Page $candidate) => $candidate->setting('project_page_key') === $key);
            $this->assertNotNull($page);
            $this->assertSame($count, $page->blocks()->count(), 'Curated page block count changed for '.$key);
            $this->assertTrue($page->blocks()->where('type', 'html')->count() < $page->blocks()->count());
        }

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

        $patternsGroup = NavigationItem::query()->forSite($site->id)->forMenu(NavigationItem::MENU_DOCS)->where('title', 'Patterns')->firstOrFail();
        $this->assertSame(NavigationItem::LINK_GROUP, $patternsGroup->link_type);
        $this->assertSame(10, NavigationItem::query()->forSite($site->id)->forMenu(NavigationItem::MENU_DOCS)->where('parent_id', $patternsGroup->id)->count());
        $this->assertSame(1, NavigationItem::query()->forSite($site->id)->forMenu(NavigationItem::MENU_DOCS)->where('parent_id', $patternsGroup->id)->where('title', 'Admin Standards')->count());

        $pageCount = Page::query()->count();
        $slotCount = PageSlot::query()->count();
        $blockCount = Block::query()->count();
        $navCount = NavigationItem::query()->count();
        $translationCount = PageTranslation::query()->count();

        $rerun = $this->artisan('project:webblocksui-import remaining-docs-html');
        $rerun->expectsOutput('Remaining HTML docs created: 0');
        $rerun->expectsOutput('Remaining HTML docs updated: 0');
        $rerun->expectsOutput('Remaining HTML docs skipped: 12');
        $rerun->assertExitCode(0);

        $this->assertSame($pageCount, Page::query()->count());
        $this->assertSame($slotCount, PageSlot::query()->count());
        $this->assertSame($blockCount, Block::query()->count());
        $this->assertSame($navCount, NavigationItem::query()->count());
        $this->assertSame($translationCount, PageTranslation::query()->count());
        $this->assertSame(1, Page::query()->where('site_id', $site->id)->get()->filter(fn (Page $page) => $page->setting('project_page_key') === 'docs-patterns')->count());
        $this->assertSame(1, Block::query()->where('page_id', $patternsPage->id)->where('type', 'html')->count());
    }

    #[Test]
    public function force_html_updates_existing_fast_html_page_without_touching_curated_pages(): void
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

        Http::fake([
            'https://ui.webblocksui.com/docs/patterns.html' => Http::response($this->patternsHtml('First import body'), 200),
            'https://ui.webblocksui.com/docs/pattern-dashboard-shell.html' => Http::response($this->genericDocHtml('Dashboard Shell', 'pattern-dashboard-shell', 'Patterns', 'Dashboard Shell', 'pattern-settings-shell.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-settings-shell.html' => Http::response($this->genericDocHtml('Settings Shell', 'pattern-settings-shell', 'Patterns', 'Settings Shell', 'pattern-admin-standards.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-admin-standards.html' => Http::response($this->genericDocHtml('Admin Standards', 'pattern-admin-standards', 'Patterns', 'Admin Standards', 'pattern-auth-shell.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-auth-shell.html' => Http::response($this->genericDocHtml('Auth Shell', 'pattern-auth-shell', 'Patterns', 'Auth Shell', 'pattern-content-shell.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-content-shell.html' => Http::response($this->genericDocHtml('Content Shell', 'pattern-content-shell', 'Patterns', 'Content Shell', 'pattern-breadcrumb.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-breadcrumb.html' => Http::response($this->genericDocHtml('Breadcrumb', 'pattern-breadcrumb', 'Patterns', 'Breadcrumb', 'pattern-gallery.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-gallery.html' => Http::response($this->genericDocHtml('Gallery', 'pattern-gallery', 'Patterns', 'Gallery', 'pattern-cookie-consent.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-cookie-consent.html' => Http::response($this->genericDocHtml('Cookie Consent', 'pattern-cookie-consent', 'Patterns', 'Cookie Consent', 'pattern-marketing.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-marketing.html' => Http::response($this->genericDocHtml('Marketing', 'pattern-marketing', 'Patterns', 'Marketing', 'utilities.html'), 200),
            'https://ui.webblocksui.com/docs/utilities.html' => Http::response($this->genericDocHtml('Utilities', 'utilities', 'WebBlocks UI', 'Utilities', 'javascript.html'), 200),
            'https://ui.webblocksui.com/docs/javascript.html' => Http::response($this->javascriptHtml(), 200),
        ]);

        $this->assertSame(0, Artisan::call('project:webblocksui-import', ['key' => 'remaining-docs-html']), Artisan::output());

        Http::fake([
            'https://ui.webblocksui.com/docs/patterns.html' => Http::response($this->patternsHtml('Updated import body'), 200),
            'https://ui.webblocksui.com/docs/pattern-dashboard-shell.html' => Http::response($this->genericDocHtml('Dashboard Shell', 'pattern-dashboard-shell', 'Patterns', 'Dashboard Shell', 'pattern-settings-shell.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-settings-shell.html' => Http::response($this->genericDocHtml('Settings Shell', 'pattern-settings-shell', 'Patterns', 'Settings Shell', 'pattern-admin-standards.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-admin-standards.html' => Http::response($this->genericDocHtml('Admin Standards', 'pattern-admin-standards', 'Patterns', 'Admin Standards', 'pattern-auth-shell.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-auth-shell.html' => Http::response($this->genericDocHtml('Auth Shell', 'pattern-auth-shell', 'Patterns', 'Auth Shell', 'pattern-content-shell.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-content-shell.html' => Http::response($this->genericDocHtml('Content Shell', 'pattern-content-shell', 'Patterns', 'Content Shell', 'pattern-breadcrumb.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-breadcrumb.html' => Http::response($this->genericDocHtml('Breadcrumb', 'pattern-breadcrumb', 'Patterns', 'Breadcrumb', 'pattern-gallery.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-gallery.html' => Http::response($this->genericDocHtml('Gallery', 'pattern-gallery', 'Patterns', 'Gallery', 'pattern-cookie-consent.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-cookie-consent.html' => Http::response($this->genericDocHtml('Cookie Consent', 'pattern-cookie-consent', 'Patterns', 'Cookie Consent', 'pattern-marketing.html'), 200),
            'https://ui.webblocksui.com/docs/pattern-marketing.html' => Http::response($this->genericDocHtml('Marketing', 'pattern-marketing', 'Patterns', 'Marketing', 'utilities.html'), 200),
            'https://ui.webblocksui.com/docs/utilities.html' => Http::response($this->genericDocHtml('Utilities', 'utilities', 'WebBlocks UI', 'Utilities', 'javascript.html'), 200),
            'https://ui.webblocksui.com/docs/javascript.html' => Http::response($this->javascriptHtml(), 200),
        ]);

        $forceExitCode = Artisan::call('project:webblocksui-import', ['key' => 'remaining-docs-html', '--force-html' => true]);
        $forceOutput = Artisan::output();

        $this->assertSame(0, $forceExitCode, $forceOutput);
        $this->assertStringContainsString('Remaining HTML docs updated: 12', $forceOutput);
        $this->assertStringContainsString('Imported payload key: docs-patterns', $forceOutput);

        $patternsPageId = PageTranslation::query()->where('site_id', $site->id)->where('slug', 'patterns')->value('page_id');
        $patternsPage = $patternsPageId ? Page::query()->with('blocks.textTranslations')->find($patternsPageId) : null;
        $this->assertNotNull($patternsPage);
        $this->assertSame(1, $patternsPage->blocks()->where('type', 'html')->count());

        $architecture = Page::query()->where('site_id', $site->id)->get()->first(fn (Page $page) => $page->setting('project_page_key') === 'docs-architecture');
        $this->assertNotNull($architecture);
        $this->assertTrue($architecture->blocks()->where('type', 'html')->count() < $architecture->blocks()->count());
    }

    private function patternsHtml(string $bodyText = 'Pattern body copy'): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Patterns</title>
  <script src="version.js"></script>
</head>
<body>
  <div class="wb-dashboard-shell">
    <aside class="wb-sidebar" id="docsSidebar"><nav class="wb-sidebar-nav"><a href="patterns.html">Patterns</a></nav></aside>
    <div class="wb-dashboard-body">
      <header class="wb-navbar wb-docs-topbar"><nav class="wb-breadcrumb wb-docs-breadcrumb">breadcrumb</nav></header>
      <main class="wb-dashboard-main">
        <div class="wb-settings-shell wb-docs-layout">
          <div class="wb-settings-body">
            <div class="wb-content-shell wb-docs-main">
              <header class="wb-content-header">
                <h1 class="wb-content-title">Patterns</h1>
                <p>{$bodyText}</p>
              </header>
              <div class="wb-content-body">
                <div class="wb-docs-flow">
                  <section id="intro">
                    <p>See <a href="utilities.html">Utilities</a> and <a href="pattern-admin-standards.html">Admin Standards</a>.</p>
                    <img src="../assets/example.jpg" alt="Example">
                    <script>alert('remove');</script>
                  </section>
                </div>
              </div>
              <footer class="wb-content-footer"><a href="utilities.html">Next</a></footer>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
  <div id="wb-overlay-root">overlay</div>
</body>
</html>
HTML;
    }

    private function javascriptHtml(): string
    {
        return $this->genericDocHtml('JavaScript', 'javascript', 'WebBlocks UI', 'JavaScript', null);
    }

    private function genericDocHtml(string $title, string $id, string $crumb, string $heading, ?string $nextHref): string
    {
        $next = $nextHref ? '<a href="'.$nextHref.'">Next</a>' : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><title>{$title}</title><script src="boot.js"></script></head>
<body>
  <div class="wb-dashboard-shell">
    <aside class="wb-sidebar" id="docsSidebar"><nav class="wb-sidebar-nav"><a href="index.html">Home</a></nav></aside>
    <div class="wb-dashboard-body">
      <header class="wb-navbar wb-docs-topbar"><nav class="wb-breadcrumb wb-docs-breadcrumb">{$crumb}</nav></header>
      <main class="wb-dashboard-main">
        <div class="wb-settings-shell wb-docs-layout">
          <div class="wb-settings-body">
            <div class="wb-content-shell wb-docs-main">
              <header class="wb-content-header"><h1 class="wb-content-title">{$heading}</h1></header>
              <div class="wb-content-body"><section id="{$id}"><p>{$title} content</p></section></div>
              <footer class="wb-content-footer">{$next}</footer>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
</body>
</html>
HTML;
    }
}
