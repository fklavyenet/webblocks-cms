<?php

namespace Tests\Feature\Console;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\PublicSearchIndex;
use App\Models\Site;
use App\Models\SlotType;
use App\Support\Blocks\BlockTranslationWriter;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SearchRebuildCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function command_indexes_published_pages_and_is_idempotent(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);

        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $slotType = SlotType::query()->updateOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true]);
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Searchable',
            'slug' => 'searchable',
            'status' => Page::STATUS_PUBLISHED,
            'settings' => ['public_shell' => 'default'],
        ]);
        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $site->id, 'name' => 'Searchable', 'slug' => 'searchable', 'path' => '/p/searchable'],
        );
        $page->slots()->create(['slot_type_id' => $slotType->id, 'source_type' => PageSlot::SOURCE_TYPE_PAGE, 'sort_order' => 0]);
        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'plain_text',
            'block_type_id' => $plainTextType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);
        $block->textTranslations()->create(['locale_id' => Page::defaultLocaleId(), 'content' => 'Command searchable text']);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        $this->artisan('search:rebuild')
            ->expectsOutputToContain('Indexed rows: 1')
            ->assertExitCode(0);

        $this->artisan('search:rebuild --site=default')
            ->expectsOutputToContain('Indexed rows: 1')
            ->assertExitCode(0);

        $this->assertSame(1, PublicSearchIndex::query()->count());
    }

    #[Test]
    public function command_reports_missing_search_table_clearly(): void
    {
        Schema::dropIfExists('public_search_index');

        $this->artisan('search:rebuild')
            ->expectsOutputToContain('Public search index table is missing. Run `ddev artisan migrate` first.')
            ->assertExitCode(1);
    }

    #[Test]
    public function command_reports_meaningful_indexed_and_skipped_counts(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);

        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $turkish = \App\Models\Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        $slotType = SlotType::query()->updateOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true]);
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Foundation',
            'slug' => 'foundation',
            'status' => Page::STATUS_PUBLISHED,
            'settings' => ['public_shell' => 'docs'],
        ]);
        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $site->id, 'name' => 'Foundation', 'slug' => 'foundation', 'path' => '/p/foundation'],
        );
        $page->slots()->create(['slot_type_id' => $slotType->id, 'source_type' => PageSlot::SOURCE_TYPE_PAGE, 'sort_order' => 0]);
        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'plain_text',
            'block_type_id' => $plainTextType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);
        $block->textTranslations()->create(['locale_id' => Page::defaultLocaleId(), 'content' => 'Foundation body']);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        $this->artisan('search:rebuild')
            ->expectsOutputToContain('Indexed rows: 1')
            ->expectsOutputToContain('Skipped pages/locales: 1')
            ->assertExitCode(0);
    }
}
