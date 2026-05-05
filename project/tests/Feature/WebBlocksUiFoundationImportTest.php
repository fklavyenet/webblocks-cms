<?php

namespace Project\Tests\Feature;

use App\Models\Block;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Project\Support\UiDocs\WebBlocksUiImporter;
use Tests\TestCase;

class WebBlocksUiFoundationImportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function foundation_page_import_is_idempotent_and_uses_structured_blocks(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);

        $this->artisan('project:webblocksui-setup-site')->assertExitCode(0);

        $site = Site::query()->where('handle', 'ui-docs-webblocksui-com')->firstOrFail();
        $home = Page::query()
            ->where('site_id', $site->id)
            ->whereHas('translations', fn ($query) => $query->where('slug', 'home'))
            ->firstOrFail();

        $importer = $this->app->make(WebBlocksUiImporter::class);
        $importer->run('docs-architecture');

        $result = $importer->run('docs-foundation');

        $this->assertContains('Source URL: https://webblocksui.com/docs/foundation.html', $result);
        $this->assertContains('Foundation local preview URL: https://'.$site->domain.'/p/foundation', $result);

        $this->assertDatabaseHas('page_translations', [
            'site_id' => $site->id,
            'name' => 'Foundation',
            'slug' => 'foundation',
            'path' => '/p/foundation',
        ]);

        $foundationPageId = PageTranslation::query()
            ->where('site_id', $site->id)
            ->where('slug', 'foundation')
            ->value('page_id');

        $foundationPage = $foundationPageId
            ? Page::query()->with(['translations', 'slots.slotType', 'blocks.textTranslations'])->find($foundationPageId)
            : null;

        $this->assertNotNull($foundationPage);
        $this->assertSame('docs-foundation', $foundationPage->setting('project_page_key'));
        $this->assertSame('docs', $foundationPage->publicShellPreset());
        $this->assertSame('/p/foundation', $foundationPage->publicPath());
        $this->assertSame('/docs/foundation.html', $foundationPage->setting('requested_public_path'));
        $this->assertSame('https://webblocksui.com/docs/foundation.html', $foundationPage->setting('source_url'));
        $this->assertSame(
            ['header', 'sidebar', 'main'],
            $foundationPage->slots->sortBy('sort_order')->pluck('slotType.slug')->values()->all(),
        );

        $titles = $foundationPage->blocks
            ->map(fn (Block $block) => $block->translatedTextFieldValue('title') ?? $block->title)
            ->filter()
            ->values()
            ->all();

        $this->assertContains('Foundation', $titles);
        $this->assertContains('Theme axes on `<html>`', $titles);
        $this->assertContains('Token groups', $titles);
        $this->assertContains('Spacing scale', $titles);
        $this->assertContains('Base element styling', $titles);
        $this->assertContains('Theme controls the package already ships', $titles);

        $themeAxesCode = $foundationPage->blocks
            ->first(fn (Block $block) => $block->typeSlug() === 'code' && str_contains((string) $block->content, 'data-mode="auto"'));
        $themeControlsCode = $foundationPage->blocks
            ->first(fn (Block $block) => $block->typeSlug() === 'code' && str_contains((string) $block->content, 'data-wb-mode-set="dark"'));
        $axisTable = $foundationPage->blocks
            ->first(fn (Block $block) => $block->typeSlug() === 'table' && str_contains((string) $block->content, 'data-mode | light, dark, auto'));
        $rawHtmlBlob = $foundationPage->blocks
            ->first(fn (Block $block) => $block->typeSlug() === 'html' && str_contains((string) $block->content, 'data-mode="auto"'));

        $this->assertNotNull($themeAxesCode);
        $this->assertNotNull($themeControlsCode);
        $this->assertNotNull($axisTable);
        $this->assertNull($rawHtmlBlob);
        $this->assertSame('html', $themeAxesCode->setting('language'));
        $this->assertSame('html', $themeControlsCode->setting('language'));

        $this->assertSame(
            ['theme-axes', 'token-groups', 'spacing-scale', 'base-element-styling', 'theme-controls'],
            $foundationPage->blocks
                ->where('type', 'heading')
                ->sortBy('sort_order')
                ->pluck('url')
                ->values()
                ->all(),
        );

        $this->assertSame('/p/foundation', $foundationPage->publicPath());
        $this->assertTrue($foundationPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'toc'));
        $this->assertTrue($foundationPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'callout' && $block->title === 'Token rule'));
        $this->assertTrue($foundationPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'quote' && str_contains((string) $block->content, 'default reading feel')));
        $this->assertTrue($foundationPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'link-list-item' && $block->url === '/p/architecture'));
        $this->assertTrue($foundationPage->blocks->contains(fn (Block $block) => $block->typeSlug() === 'link-list-item' && $block->url === 'layout.html'));

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

        $rerun = $this->artisan('project:webblocksui-import docs-foundation');
        $rerun->expectsOutput('Source URL: https://webblocksui.com/docs/foundation.html');
        $rerun->expectsOutput('Foundation local preview URL: https://'.$site->domain.'/p/foundation');
        $rerun->assertExitCode(0);

        $this->assertSame($firstPageCount, Page::query()->count());
        $this->assertSame($firstSlotCount, PageSlot::query()->count());
        $this->assertSame($firstBlockCount, Block::query()->count());
        $this->assertSame($firstNavigationCount, NavigationItem::query()->count());
        $this->assertSame($firstTranslationCount, PageTranslation::query()->count());
        $this->assertSame(
            1,
            Page::query()->where('site_id', $site->id)->get()->filter(fn (Page $page) => $page->setting('project_page_key') === 'docs-foundation')->count(),
        );
        $this->assertSame(
            1,
            NavigationItem::query()->forSite($site->id)->forMenu(NavigationItem::MENU_DOCS)->where('title', 'Foundation')->count(),
        );
    }

}
