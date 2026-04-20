<?php

namespace Tests\Feature;

use App\Models\Locale;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Support\Pages\PageRouteResolver;
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
            'site_id' => $site->id,
            'locale_id' => $turkish->id,
            'name' => 'Ana Sayfa',
            'slug' => 'home',
            'path' => '/',
        ]);

        PageTranslation::query()->create([
            'page_id' => $about->id,
            'site_id' => $site->id,
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
}
