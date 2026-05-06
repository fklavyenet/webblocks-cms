<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\SlotType;
use App\Models\Site;
use App\Support\Blocks\BlockTranslationWriter;
use App\Support\Search\PublicSearchIndexer;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicSearchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function search_route_returns_matching_result_for_current_site_and_locale(): void
    {
        [$site, $locale, $slotType, $plainTextType] = $this->seedSearchFoundation();
        $this->pageWithText($site, $locale, $slotType, $plainTextType, 'Alpha Title', 'alpha-title', 'Alpha content');
        app(PublicSearchIndexer::class)->rebuild();

        $response = $this->get('/search?q=Alpha');

        $response->assertOk();
        $response->assertSee('Alpha Title');
        $response->assertSee('/p/alpha-title');
    }

    #[Test]
    public function empty_and_short_queries_show_safe_prompt_states(): void
    {
        $this->seedSearchFoundation();

        $this->get('/search')->assertOk()->assertSee('Enter a search term');
        $this->get('/search?q=a')->assertOk()->assertSee('Enter at least 2 characters');
    }

    #[Test]
    public function no_results_and_xss_queries_render_escaped_output(): void
    {
        $this->seedSearchFoundation();

        $response = $this->get('/search?q=%3Cscript%3Ealert(1)%3C%2Fscript%3E');

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('No results matched');
        $response->assertSee('alert(1)');
    }

    #[Test]
    public function localized_search_route_uses_current_locale_scope(): void
    {
        [$site, $locale, $slotType, $plainTextType] = $this->seedSearchFoundation();
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        $page = $this->pageWithText($site, $locale, $slotType, $plainTextType, 'English Title', 'english-title', 'English body');
        PageTranslation::query()->create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'locale_id' => $turkish->id,
            'name' => 'Turkce Sonuc',
            'slug' => 'turkce-sonuc',
            'path' => '/p/turkce-sonuc',
        ]);
        $page->blocks()->each(function (Block $block) use ($turkish) {
            $block->textTranslations()->updateOrCreate(['locale_id' => $turkish->id], ['content' => 'Merhaba arama']);
            app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));
        });

        app(PublicSearchIndexer::class)->rebuild();

        $this->get('/tr/search?q=arama')
            ->assertOk()
            ->assertSee('Turkce Sonuc')
            ->assertSee('/tr/p/turkce-sonuc');
    }

    private function seedSearchFoundation(): array
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);

        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $locale = Locale::query()->where('is_default', true)->firstOrFail();
        $slotType = SlotType::query()->updateOrCreate(
            ['slug' => 'main'],
            ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

        return [$site, $locale, $slotType, $plainTextType];
    }

    private function pageWithText(Site $site, Locale $locale, SlotType $slotType, BlockType $plainTextType, string $title, string $slug, string $content): Page
    {
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => $title,
            'slug' => $slug,
            'status' => Page::STATUS_PUBLISHED,
            'settings' => ['public_shell' => 'default'],
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => $locale->id],
            ['site_id' => $site->id, 'name' => $title, 'slug' => $slug, 'path' => '/p/'.$slug],
        );

        $page->slots()->create([
            'slot_type_id' => $slotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'sort_order' => 0,
        ]);

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
        $block->textTranslations()->create(['locale_id' => $locale->id, 'content' => $content]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        return $page;
    }
}
