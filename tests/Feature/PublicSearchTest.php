<?php

namespace Tests\Feature;

use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Block as PackageBlock;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageAsset;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteVariable;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\Blocks\BlockTranslationWriter;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;

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
    $response->assertSee('/alpha-title');
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
    $page->blocks()->each(function (PackageBlock $block) use ($turkish) {
      $block->textTranslations()->updateOrCreate(['locale_id' => $turkish->id], ['content' => 'Merhaba arama']);
      app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));
    });

    app(PublicSearchIndexer::class)->rebuild();

    $this->get('/tr/search?q=arama')
      ->assertOk()
      ->assertSee('Turkce Sonuc')
      ->assertSee('/tr/turkce-sonuc');
  }

  #[Test]
  public function search_json_route_returns_results_for_current_site_and_locale(): void
  {
    [$site, $locale, $slotType, $plainTextType] = $this->seedSearchFoundation();
    $this->pageWithText($site, $locale, $slotType, $plainTextType, 'Alpha Title', 'alpha-title', 'Alpha content');
    app(PublicSearchIndexer::class)->rebuild();

    $response = $this->getJson('/search.json?q=Alpha');

    $response->assertOk()
      ->assertJsonPath('query', 'Alpha')
      ->assertJsonPath('count', 1)
      ->assertJsonPath('minimum_length', 2)
      ->assertJsonPath('results.0.title', 'Alpha Title')
      ->assertJsonPath('results.0.url', '/alpha-title');
  }

  #[Test]
  public function search_json_route_does_not_leak_other_site_or_draft_results(): void
  {
    [$site, $locale, $slotType, $plainTextType] = $this->seedSearchFoundation();
    $this->pageWithText($site, $locale, $slotType, $plainTextType, 'Visible Alpha', 'visible-alpha', 'Alpha content');

    $otherSite = Site::query()->create([
      'name' => 'Other Site',
      'slug' => 'other-site',
      'domain' => 'other.example.test',
      'is_primary' => false,
    ]);
    $otherSite->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
    $this->pageWithText($otherSite, $locale, $slotType, $plainTextType, 'Other Alpha', 'other-alpha', 'Alpha content');

    $draft = $this->pageWithText($site, $locale, $slotType, $plainTextType, 'Draft Alpha', 'draft-alpha', 'Alpha content');
    $draft->update(['status' => Page::STATUS_DRAFT]);

    app(PublicSearchIndexer::class)->rebuild();

    $response = $this->getJson('/search.json?q=Alpha');

    $response->assertOk();
    $response->assertJsonPath('count', 1);
    $response->assertJsonMissing(['title' => 'Other Alpha']);
    $response->assertJsonMissing(['title' => 'Draft Alpha']);
    $response->assertJsonPath('results.0.title', 'Visible Alpha');
  }

  #[Test]
  public function search_json_route_handles_empty_and_short_queries_safely(): void
  {
    $this->seedSearchFoundation();

    $this->getJson('/search.json')
      ->assertOk()
      ->assertJsonPath('query', '')
      ->assertJsonPath('count', 0)
      ->assertJsonPath('minimum_query_length', null)
      ->assertJsonPath('no_results', null)
      ->assertJsonPath('results', []);

    $this->getJson('/search.json?q=a')
      ->assertOk()
      ->assertJsonPath('query', 'a')
      ->assertJsonPath('count', 0)
      ->assertJsonPath('minimum_query_length', 'Enter at least 2 characters to search.')
      ->assertJsonPath('results', []);
  }

  #[Test]
  public function search_json_route_returns_safe_strings_for_dangerous_queries_and_results(): void
  {
    [$site, $locale, $slotType, $plainTextType] = $this->seedSearchFoundation();
    $this->pageWithText($site, $locale, $slotType, $plainTextType, '<script>alert(2)</script>', 'dangerous-title', 'Alpha <img src=x onerror=alert(3)> content');
    app(PublicSearchIndexer::class)->rebuild();

    $response = $this->getJson('/search.json?q=%3Cscript%3Ealert(2)%3C%2Fscript%3E');

    $response->assertOk();
    $response->assertJsonPath('query', 'alert(2)');
    $response->assertJsonPath('count', 1);
    $response->assertJsonPath('results.0.title', 'alert(2)');
    $response->assertJsonPath('results.0.url', '/dangerous-title');
    $this->assertIsString($response->json('results.0.excerpt'));
  }

  #[Test]
  public function foundation_like_docs_page_is_searchable_through_public_search(): void
  {
    [$site, $locale, $slotType, $plainTextType] = $this->seedSearchFoundation();

    $page = $this->pageWithText($site, $locale, $slotType, $plainTextType, 'Foundation', 'foundation', 'Design tokens and layout foundations');
    $page->update(['settings' => ['public_shell' => 'docs']]);

    app(PublicSearchIndexer::class)->rebuild();

    $this->get('/search?q=foundation')
      ->assertOk()
      ->assertSee('Foundation')
      ->assertSee('/p/foundation');
  }

  #[Test]
  public function page_asset_paths_are_not_indexed_as_search_content(): void
  {
    [$site, $locale, $slotType, $plainTextType] = $this->seedSearchFoundation();
    $page = $this->pageWithText($site, $locale, $slotType, $plainTextType, 'Alpha Title', 'alpha-title', 'Alpha content');

    PageAsset::query()->create([
      'page_id' => $page->id,
      'type' => 'js',
      'path' => '/site/webblocks-ui/pages/playground/page.js',
      'load_position' => 'body_end',
      'is_enabled' => true,
      'sort_order' => 0,
    ]);

    app(PublicSearchIndexer::class)->rebuild();

    $this->get('/search?q=playground.js')
      ->assertOk()
      ->assertSee('No results matched');
  }

  #[Test]
  public function search_modal_description_includes_the_resolved_site_label(): void
  {
    [$site] = $this->seedSearchFoundation();
    $site->update([
      'display_name' => 'Docs Portal',
      'seo_title' => 'Docs SEO Title',
    ]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Search published content in Docs Portal.');
    $response->assertDontSee('Search published content for this site.');
  }

  #[Test]
  public function search_modal_description_is_scoped_to_the_current_site_and_not_project_name(): void
  {
    [$site, $locale, $slotType, $plainTextType] = $this->seedSearchFoundation();

    SystemSetting::query()->updateOrCreate(['key' => 'system.project_name'], ['value' => 'Admin Project']);

    $campaignSite = Site::query()->create([
      'name' => 'Campaign Admin Name',
      'display_name' => 'Campaign Public Name',
      'handle' => 'campaign',
      'domain' => 'campaign.example.test',
      'is_primary' => false,
    ]);
    $campaignSite->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
    $this->pageWithText($campaignSite, $locale, $slotType, $plainTextType, 'Campaign Home', 'campaign-home', 'Campaign content');

    $response = $this->get('http://campaign.example.test/campaign-home');

    $response->assertOk();
    $response->assertSee('Search published content in Campaign Public Name.');
    $response->assertDontSee('Admin Project');
  }

  #[Test]
  public function search_indexes_resolved_site_variable_values_instead_of_raw_tokens(): void
  {
    [$site, $locale, $slotType, $plainTextType] = $this->seedSearchFoundation();
    SiteVariable::query()->create([
      'site_id' => $site->id,
      'key' => 'support_email',
      'label' => 'Support Email',
      'value' => 'support@example.test',
      'is_enabled' => true,
    ]);

    $this->pageWithText($site, $locale, $slotType, $plainTextType, 'Alpha Title', 'alpha-title', 'Contact {{ site.support_email }} today');
    app(PublicSearchIndexer::class)->rebuild();

    $this->get('/search?q=support@example.test')
      ->assertOk()
      ->assertSee('Alpha Title');

    $this->get('/search?q=site.support_email')
      ->assertOk()
      ->assertSee('No results matched');
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
