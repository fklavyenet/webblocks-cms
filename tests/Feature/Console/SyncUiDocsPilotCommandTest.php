<?php

namespace Tests\Feature\Console;

use App\Models\Block;
use App\Models\BlockTextTranslation;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncUiDocsPilotCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sync_ui_docs_pilot_rebuilds_target_pages_idempotently_and_keeps_other_sites_unchanged(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $defaultSite = Site::query()->firstOrFail();
        $defaultSite->update(['domain' => 'default.example.test']);

        $targetSite = Site::query()->create([
            'name' => 'UI Docs',
            'handle' => 'ui-docs',
            'domain' => 'ui.docs.webblocksui.com',
            'is_primary' => false,
        ]);

        $otherSite = Site::query()->create([
            'name' => 'CMS Docs',
            'handle' => 'cms-docs',
            'domain' => 'cms.docs.webblocksui.com',
            'is_primary' => false,
        ]);

        $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
        $turkishLocale = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);

        $targetSite->locales()->syncWithoutDetaching([
            $defaultLocale->id => ['is_enabled' => true],
            $turkishLocale->id => ['is_enabled' => true],
        ]);
        $otherSite->locales()->syncWithoutDetaching([
            $defaultLocale->id => ['is_enabled' => true],
        ]);

        $mainSlot = SlotType::query()->updateOrCreate(
            ['slug' => 'main'],
            ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );

        $existingHome = Page::query()->create([
            'site_id' => $targetSite->id,
            'title' => 'Old Home',
            'slug' => 'home',
            'page_type' => 'docs',
            'status' => Page::STATUS_PUBLISHED,
        ]);
        PageSlot::query()->create([
            'page_id' => $existingHome->id,
            'slot_type_id' => $mainSlot->id,
            'sort_order' => 0,
        ]);
        $staleBlock = Block::query()->create([
            'page_id' => $existingHome->id,
            'type' => 'text',
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlot->id,
            'sort_order' => 0,
            'title' => 'Stale pilot block',
            'content' => 'Remove this experimental content',
            'status' => 'published',
            'is_system' => false,
        ]);

        $otherPage = Page::query()->create([
            'site_id' => $otherSite->id,
            'title' => 'Other Home',
            'slug' => 'home',
            'page_type' => 'docs',
            'status' => Page::STATUS_PUBLISHED,
        ]);
        PageSlot::query()->create([
            'page_id' => $otherPage->id,
            'slot_type_id' => $mainSlot->id,
            'sort_order' => 0,
        ]);
        Block::query()->create([
            'page_id' => $otherPage->id,
            'type' => 'text',
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlot->id,
            'sort_order' => 0,
            'title' => 'Leave other sites alone',
            'content' => 'This block should stay untouched',
            'status' => 'published',
            'is_system' => false,
        ]);

        $this->artisan('webblocks:sync-ui-docs-pilot')
            ->expectsOutputToContain('target site: UI Docs | ui.docs.webblocksui.com')
            ->expectsOutputToContain('pages synced: 3')
            ->expectsOutputToContain('pages rebuilt: home, getting-started, cookie-consent')
            ->expectsOutputToContain('Code Example Block needed')
            ->assertExitCode(0);

        $pilotPages = Page::query()
            ->where('site_id', $targetSite->id)
            ->whereHas('translations', fn ($query) => $query->whereIn('slug', ['home', 'getting-started', 'cookie-consent']))
            ->with(['translations', 'blocks.textTranslations'])
            ->get();

        $this->assertCount(3, $pilotPages);
        $this->assertSame($existingHome->id, $pilotPages->firstWhere('id', $existingHome->id)?->id);
        $this->assertDatabaseMissing('blocks', ['id' => $staleBlock->id]);

        foreach (['home', 'getting-started', 'cookie-consent'] as $slug) {
            $page = Page::query()
                ->where('site_id', $targetSite->id)
                ->whereHas('translations', fn ($query) => $query->where('slug', $slug))
                ->firstOrFail();

            $this->assertSame(2, PageTranslation::query()->where('page_id', $page->id)->count());
            $this->assertGreaterThan(0, Block::query()->where('page_id', $page->id)->count());
        }

        $homeHeading = Block::query()
            ->where('page_id', $existingHome->id)
            ->where('type', 'heading')
            ->firstOrFail();

        $this->assertNull($homeHeading->getRawOriginal('title'));
        $this->assertSame(2, BlockTextTranslation::query()->where('block_id', $homeHeading->id)->count());
        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $homeHeading->id,
            'locale_id' => $defaultLocale->id,
            'title' => 'Build consistent interfaces with WebBlocks UI',
        ]);
        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $homeHeading->id,
            'locale_id' => $turkishLocale->id,
            'title' => 'Build consistent interfaces with WebBlocks UI',
        ]);

        $targetBlockCount = Block::query()->whereIn('page_id', $pilotPages->pluck('id'))->count();

        $this->artisan('webblocks:sync-ui-docs-pilot')
            ->expectsOutputToContain('pages synced: 3')
            ->assertExitCode(0);

        $this->assertCount(3, Page::query()->where('site_id', $targetSite->id)->whereHas('translations', fn ($query) => $query->whereIn('slug', ['home', 'getting-started', 'cookie-consent']))->get());
        $this->assertSame($targetBlockCount, Block::query()->whereIn('page_id', $pilotPages->pluck('id'))->count());
        $this->assertSame(1, Block::query()->where('page_id', $otherPage->id)->count());
        $this->assertSame('Leave other sites alone', Block::query()->where('page_id', $otherPage->id)->firstOrFail()->title);

        $this->get('http://ui.docs.webblocksui.com/')
            ->assertOk()
            ->assertSee('Build consistent interfaces with WebBlocks UI')
            ->assertSee('wb-section', false)
            ->assertSee('wb-grid', false)
            ->assertSee('wb-alert', false)
            ->assertSee('wb-link-list', false);

        $this->get('http://ui.docs.webblocksui.com/p/getting-started')
            ->assertOk()
            ->assertSee('Load WebBlocks UI from jsDelivr')
            ->assertSee('<pre><code data-language="html">', false)
            ->assertSee('wb-card', false);

        $this->get('http://ui.docs.webblocksui.com/p/cookie-consent')
            ->assertOk()
            ->assertSee('Cookie Consent')
            ->assertSee('Implementation notes')
            ->assertSee('wb-link-list', false);
    }

    #[Test]
    public function sync_ui_docs_pilot_fails_clearly_when_target_site_is_missing(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $this->artisan('webblocks:sync-ui-docs-pilot')
            ->expectsOutputToContain('Site not found for domain ui.docs.webblocksui.com')
            ->assertExitCode(1);
    }
}
