<?php

namespace Tests\Feature;

use App\Models\Locale;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Support\Pages\PageRouteResolver;
use App\Support\Sites\SiteResolver;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MultisiteMultilingualFoundationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function default_site_and_default_locale_are_seeded(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $this->assertDatabaseHas('sites', [
            'handle' => 'default',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('locales', [
            'code' => 'en',
            'is_default' => true,
            'is_enabled' => true,
        ]);
    }

    #[Test]
    public function existing_pages_are_backfilled_to_the_default_site_and_english_translation_during_migration(): void
    {
        Schema::dropIfExists('block_contact_form_translations');
        Schema::dropIfExists('block_image_translations');
        Schema::dropIfExists('block_button_translations');
        Schema::dropIfExists('block_text_translations');
        Schema::dropIfExists('page_translations');
        Schema::dropIfExists('site_locales');
        Schema::dropIfExists('locales');
        Schema::dropIfExists('sites');

        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['site_id']);
            $table->dropColumn('site_id');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->unique('slug');
        });

        DB::table('pages')->insert([
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
        $this->assertDatabaseHas('page_translations', [
            'page_id' => $page->id,
            'name' => 'Legacy About',
            'slug' => 'legacy-about-two',
        ]);
        $this->assertFalse(Schema::hasColumn('page_translations', 'site_id'));
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

        $this->assertSame('/p/about', $page->publicPath());
        $this->assertSame('/tr/p/hakkinda', $page->publicPath('tr'));
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
            'path' => '/p/precos',
        ]);

        $this->assertSame('/pt-br/p/precos', $page->publicPath('pt_BR'));
        $this->get('http://primary.example.test/pt-br/p/precos')->assertOk()->assertSee('Precos');
        $this->get('http://primary.example.test/pt/p/precos')->assertNotFound();
        $this->get('http://primary.example.test/pt-br-br/p/precos')->assertNotFound();
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
            'path' => '/p/hakkinda',
        ]);

        $request = request()->create('/tr/p/hakkinda', 'GET');
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $this->assertNotNull(app(PageRouteResolver::class)->findPublishedPage($request, 'hakkinda'));

        $this->get('/')->assertOk();
        $this->get('/p/about')->assertOk();
        $this->get('/tr')->assertOk();
        $this->assertSame('{locale}/p/{slug}', $route->uri());
        $this->get('/tr/p/hakkinda')->assertOk();
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

        $this->get('http://primary.example.test/p/about')
            ->assertOk()
            ->assertSee('Primary About')
            ->assertDontSee('Campaign About');

        $this->get('http://campaign.example.test/p/about')
            ->assertOk()
            ->assertSee('Campaign About')
            ->assertDontSee('Primary About');

        $this->assertSame('https://campaign.example.test/p/about', $campaignAbout->fresh()->publicUrl());
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

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/p/hakkinda',
        ]);

        $this->get('http://primary.example.test/tr/p/hakkinda')->assertNotFound();

        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        $this->get('http://primary.example.test/tr/p/hakkinda')->assertOk()->assertSee('Hakkinda');
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

        $this->get('http://unknown.example.test/p/about')->assertOk()->assertSee('About');

        config()->set('cms.multisite.unknown_host_fallback', false);

        $this->get('http://unknown.example.test/p/about')->assertNotFound();
        $this->assertSame('https://primary.example.test/p/about', $page->fresh()->publicUrl());
    }

    #[Test]
    public function site_resolver_normalizes_hosts_before_matching_sites(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->where('handle', 'default')->firstOrFail();
        $site->update(['domain' => 'primary.example.test']);

        $request = request()->create('https://PRIMARY.EXAMPLE.TEST/p/about', 'GET');
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
        $this->assertFalse(Schema::hasColumn('page_translations', 'site_id'));
        $this->assertSame($page->site_id, $translation->page->site_id);
    }
}
