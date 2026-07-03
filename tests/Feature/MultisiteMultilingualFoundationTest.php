<?php

namespace Tests\Feature;

use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;
use WebBlocks\Cms\Support\Sites\SiteResolver;

class MultisiteMultilingualFoundationTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function default_site_and_default_locale_are_seeded(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);

    $this->assertDatabaseHas('wbcms_sites', [
      'handle' => 'default',
      'is_primary' => true,
    ]);

    $this->assertDatabaseHas('wbcms_locales', [
      'code' => 'en',
      'is_default' => true,
      'is_enabled' => true,
    ]);
  }

  #[Test]
  public function existing_pages_are_backfilled_to_the_default_site_and_english_translation_during_migration(): void
  {
    Schema::dropIfExists('wbcms_public_search_index');
    Schema::dropIfExists('wbcms_page_translations');
    Schema::dropIfExists('wbcms_block_contact_form_translations');
    Schema::dropIfExists('wbcms_block_image_translations');
    Schema::dropIfExists('wbcms_block_button_translations');
    Schema::dropIfExists('wbcms_block_text_translations');
    Schema::dropIfExists('wbcms_visitor_events');
    Schema::table('wbcms_pages', function (Blueprint $table) {
      $table->dropUnique('pages_id_site_id_unique');
      $table->dropForeign(['site_id']);
      $table->dropColumn('site_id');
    });

    Schema::table('wbcms_pages', function (Blueprint $table) {
      $table->string('title')->nullable();
      $table->string('slug')->nullable();
      $table->unique('slug');
    });

    DB::statement('PRAGMA foreign_keys = OFF');
    foreach ([
      'wbcms_engagement_ratings',
      'wbcms_engagement_comments',
      'wbcms_site_imports',
      'wbcms_site_exports',
      'wbcms_site_variables',
      'wbcms_site_domains',
      'wbcms_user_site_access',
      'wbcms_public_search_index',
      'wbcms_visitor_events',
      'wbcms_shared_slot_revisions',
      'wbcms_shared_slot_blocks',
      'wbcms_shared_slots',
      'wbcms_block_media',
      'wbcms_page_slots',
      'wbcms_blocks',
      'wbcms_navigation_items',
      'wbcms_page_revisions',
      'wbcms_block_gallery_item_translations',
    ] as $table) {
      Schema::dropIfExists($table);
    }

    Schema::dropIfExists('wbcms_site_locales');
    Schema::dropIfExists('wbcms_sites');
    Schema::dropIfExists('wbcms_locales');
    DB::statement('PRAGMA foreign_keys = ON');

    DB::table('wbcms_pages')->insert([
      'title' => 'Legacy About',
      'slug' => 'legacy-about-two',
      'page_type' => 'default',
      'layout_id' => null,
      'status' => 'published',
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_04_20_130000_add_multisite_multilingual_foundation.php');
    $migration->up();

    $page = Page::query()->with(['site', 'translations'])->firstOrFail();

    $this->assertSame('default', $page->site?->handle);
    $this->assertSame('legacy-about-two', $page->defaultTranslation()?->slug);
    $this->assertSame('/p/legacy-about-two', $page->defaultTranslation()?->path);
    $this->assertDatabaseHas('wbcms_page_translations', [
      'page_id' => $page->id,
      'name' => 'Legacy About',
      'slug' => 'legacy-about-two',
    ]);
    $this->assertFalse(Schema::hasColumn('wbcms_page_translations', 'site_id'));
  }

  #[Test]
  public function default_locale_urls_are_prefixless_and_non_default_locale_urls_are_prefixed(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);

    $site = Site::query()->firstOrFail();
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'About',
      'slug' => 'about',
      'status' => 'published',
    ]);

    $turkish = Locale::query()->create([
      'code' => 'tr',
      'name' => 'Turkish',
      'is_default' => false,
      'is_enabled' => true,
    ]);
    $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

    PageTranslation::query()->create([
      'page_id' => $page->id,
      'site_id' => $site->id,
      'locale_id' => $turkish->id,
      'name' => 'Hakkinda',
      'slug' => 'hakkinda',
      'path' => '/p/hakkinda',
    ]);

    $this->assertSame('/about', $page->publicPath());
    $this->assertSame('/tr/hakkinda', $page->publicPath('tr'));
    $this->assertSame('/', app(PageRouteResolver::class)->homePath());
    $this->assertSame('/tr', app(PageRouteResolver::class)->homePath('tr'));
    $this->assertNull($page->publicPath('de'));
    $this->assertNull($page->publicUrl('de'));
  }

  #[Test]
  public function locale_codes_support_language_and_language_region_formats_consistently(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);

    $site = Site::query()->firstOrFail();
    $site->update(['domain' => 'primary.example.test']);
    $portuguese = Locale::query()->create([
      'code' => 'pt_BR',
      'name' => 'Portuguese Brazil',
      'is_default' => false,
      'is_enabled' => true,
    ]);

    $this->assertSame('pt-br', $portuguese->fresh()->code);

    $site->locales()->syncWithoutDetaching([$portuguese->id => ['is_enabled' => true]]);

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Pricing',
      'slug' => 'pricing',
      'status' => 'published',
    ]);

    PageTranslation::query()->create([
      'page_id' => $page->id,
      'locale_id' => $portuguese->id,
      'name' => 'Precos',
      'slug' => 'precos',
      'path' => '/precos',
    ]);

    $this->assertSame('/pt-br/precos', $page->publicPath('pt_BR'));
    $this->get('http://primary.example.test/pt-br/precos')->assertOk()->assertSee('Precos');
    $this->get('http://primary.example.test/pt/precos')->assertNotFound();
    $this->get('http://primary.example.test/pt-br-br/precos')->assertNotFound();
  }

  #[Test]
  public function page_lookup_works_by_site_locale_and_slug_for_home_and_non_home_routes(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);

    $site = Site::query()->firstOrFail();
    $home = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Home',
      'slug' => 'home',
      'status' => 'published',
    ]);
    $about = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'About',
      'slug' => 'about',
      'status' => 'published',
    ]);

    $turkish = Locale::query()->create([
      'code' => 'tr',
      'name' => 'Turkish',
      'is_default' => false,
      'is_enabled' => true,
    ]);

    $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

    PageTranslation::query()->create([
      'page_id' => $home->id,
      'locale_id' => $turkish->id,
      'name' => 'Ana Sayfa',
      'slug' => 'home',
      'path' => '/',
    ]);

    PageTranslation::query()->create([
      'page_id' => $about->id,
      'locale_id' => $turkish->id,
      'name' => 'Hakkinda',
      'slug' => 'hakkinda',
      'path' => '/hakkinda',
    ]);

    $request = request()->create('/tr/p/hakkinda', 'GET');
    $route = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);
    $this->assertNotNull(app(PageRouteResolver::class)->findPublishedPage($request, 'hakkinda'));

    $this->get('/')->assertOk();
    $this->get('/about')->assertOk();
    $this->get('/tr')->assertOk();
    $this->assertSame('{locale}/p/{path}', $route->uri());
    $this->get('/tr/hakkinda')->assertOk();
  }

  #[Test]
  public function host_resolution_scopes_public_pages_per_site_and_allows_overlapping_slugs(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);

    $primarySite = Site::query()->where('handle', 'default')->firstOrFail();
    $primarySite->update(['domain' => 'primary.example.test']);

    $campaignSite = Site::query()->create([
      'name' => 'Campaign',
      'handle' => 'campaign',
      'domain' => 'campaign.example.test',
      'is_primary' => false,
    ]);

    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
    $primarySite->locales()->syncWithoutDetaching([$defaultLocale->id => ['is_enabled' => true]]);
    $campaignSite->locales()->syncWithoutDetaching([$defaultLocale->id => ['is_enabled' => true]]);

    $primaryAbout = Page::query()->create([
      'site_id' => $primarySite->id,
      'title' => 'Primary About',
      'slug' => 'about',
      'status' => 'published',
    ]);

    $campaignAbout = Page::query()->create([
      'site_id' => $campaignSite->id,
      'title' => 'Campaign About',
      'slug' => 'about',
      'status' => 'published',
    ]);

    $this->get('http://primary.example.test/about')
      ->assertOk()
      ->assertSee('Primary About')
      ->assertDontSee('Campaign About');

    $this->get('http://campaign.example.test/about')
      ->assertOk()
      ->assertSee('Campaign About')
      ->assertDontSee('Primary About');

    $this->assertSame('https://campaign.example.test/about', $campaignAbout->fresh()->publicUrl());
  }

  #[Test]
  public function locale_prefix_must_be_enabled_for_the_resolved_site(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);

    $site = Site::query()->where('handle', 'default')->firstOrFail();
    $site->update(['domain' => 'primary.example.test']);
    $turkish = Locale::query()->create([
      'code' => 'tr',
      'name' => 'Turkish',
      'is_default' => false,
      'is_enabled' => true,
    ]);
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'About',
      'slug' => 'about',
      'status' => 'published',
    ]);

    $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

    PageTranslation::query()->create([
      'page_id' => $page->id,
      'locale_id' => $turkish->id,
      'name' => 'Hakkinda',
      'slug' => 'hakkinda',
      'path' => '/hakkinda',
    ]);

    DB::table('wbcms_site_locales')
      ->where('site_id', $site->id)
      ->where('locale_id', $turkish->id)
      ->update(['is_enabled' => false]);

    $this->get('http://primary.example.test/tr/hakkinda')->assertNotFound();

    $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

    $this->get('http://primary.example.test/tr/hakkinda')->assertOk()->assertSee('Hakkinda');
  }

  #[Test]
  public function invalid_locale_prefix_formats_do_not_match_public_page_routes(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);

    $site = Site::query()->where('handle', 'default')->firstOrFail();
    $site->update(['domain' => 'primary.example.test']);

    Page::query()->create([
      'site_id' => $site->id,
      'title' => 'About',
      'slug' => 'about',
      'status' => 'published',
    ]);

    $this->get('http://primary.example.test/TR/about')->assertNotFound();
    $this->get('http://primary.example.test/tr_TR/about')->assertNotFound();
    $this->get('http://primary.example.test/tur/about')->assertNotFound();
  }

  #[Test]
  public function unknown_host_falls_back_in_testing_but_can_be_disabled_explicitly(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);

    $site = Site::query()->where('handle', 'default')->firstOrFail();
    $site->update(['domain' => 'primary.example.test']);
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'About',
      'slug' => 'about',
      'status' => 'published',
    ]);

    $this->get('http://unknown.example.test/about')->assertOk()->assertSee('About');

    config()->set('cms.multisite.unknown_host_fallback', false);

    $this->get('http://unknown.example.test/about')->assertNotFound();
    $this->assertSame('https://primary.example.test/about', $page->fresh()->publicUrl());
  }

  #[Test]
  public function site_resolver_normalizes_hosts_before_matching_sites(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);

    $site = Site::query()->where('handle', 'default')->firstOrFail();
    $site->update(['domain' => 'primary.example.test']);

    $request = request()->create('https://PRIMARY.EXAMPLE.TEST/about', 'GET');
    $route = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);

    $resolved = app(SiteResolver::class)->resolve($request);

    $this->assertSame($site->id, $resolved->site->id);
    $this->assertTrue($resolved->matchedHost);
    $this->assertSame('primary.example.test', $resolved->requestedHost);
  }

  #[Test]
  public function page_translations_use_page_site_as_the_single_source_of_truth(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);

    $site = Site::query()->firstOrFail();
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'About',
      'slug' => 'about',
      'status' => 'published',
    ]);

    $translation = $page->defaultTranslation();

    $this->assertNotNull($translation);
    $this->assertTrue(Schema::hasColumn('wbcms_page_translations', 'site_id'));
    $this->assertSame($page->site_id, $translation->site_id);
    $this->assertSame($page->site_id, $translation->page->site_id);
  }
}
