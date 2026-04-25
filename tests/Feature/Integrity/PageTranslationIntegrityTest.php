<?php

namespace Tests\Feature\Integrity;

use App\Models\Locale;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Site;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PageTranslationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function defaultSite(): Site
    {
        return Site::query()->where('is_primary', true)->firstOrFail();
    }

    private function defaultLocale(): Locale
    {
        return Locale::query()->where('is_default', true)->firstOrFail();
    }

    private function createLocale(string $code): Locale
    {
        return Locale::query()->create([
            'code' => $code,
            'name' => strtoupper($code),
            'is_default' => false,
            'is_enabled' => true,
        ]);
    }

    private function createPage(Site $site, string $title, string $slug): Page
    {
        return Page::query()->create([
            'site_id' => $site->id,
            'title' => $title,
            'slug' => $slug,
            'status' => Page::STATUS_PUBLISHED,
        ]);
    }

    #[Test]
    public function cannot_create_a_duplicate_slug_for_the_same_site_and_locale(): void
    {
        $site = $this->defaultSite();

        $this->createPage($site, 'About', 'about');

        $this->expectException(QueryException::class);

        $this->createPage($site, 'About Copy', 'about');
    }

    #[Test]
    public function cannot_create_a_duplicate_path_for_the_same_site_and_locale(): void
    {
        $site = $this->defaultSite();
        $page = $this->createPage($site, 'About', 'about');

        $this->expectException(QueryException::class);

        DB::table('page_translations')->insert([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'locale_id' => $this->defaultLocale()->id,
            'name' => 'Conflicting',
            'slug' => 'conflicting-home',
            'path' => '/',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function the_same_slug_is_allowed_across_different_sites(): void
    {
        $defaultLocale = $this->defaultLocale();
        $firstSite = $this->defaultSite();
        $secondSite = Site::query()->create([
            'name' => 'Campaign',
            'handle' => 'campaign',
            'domain' => 'campaign.example.test',
            'is_primary' => false,
        ]);
        $secondSite->locales()->syncWithoutDetaching([$defaultLocale->id => ['is_enabled' => true]]);

        $firstPage = $this->createPage($firstSite, 'About', 'about');
        $secondPage = $this->createPage($secondSite, 'About', 'about');

        $this->assertSame('/p/about', $firstPage->publicPath());
        $this->assertSame('/p/about', $secondPage->publicPath());
        $this->assertSame(2, PageTranslation::query()->where('slug', 'about')->count());
    }

    #[Test]
    public function default_locale_routing_works_without_a_prefix_and_non_default_locale_routing_works_with_a_prefix(): void
    {
        $site = $this->defaultSite();
        $site->update(['domain' => 'primary.example.test']);
        $turkish = $this->createLocale('tr');
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
        $page = $this->createPage($site, 'About', 'about');

        $page->translations()->create([
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/p/hakkinda',
        ]);

        $this->get('http://primary.example.test/p/about')->assertOk()->assertSee('About');
        $this->get('http://primary.example.test/tr/p/hakkinda')->assertOk()->assertSee('Hakkinda');
        $this->get('http://primary.example.test/p/hakkinda')->assertNotFound();
    }

    #[Test]
    public function missing_translations_return_not_found_instead_of_falling_back_to_another_locale(): void
    {
        $site = $this->defaultSite();
        $site->update(['domain' => 'primary.example.test']);
        $turkish = $this->createLocale('tr');
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
        $this->createPage($site, 'About', 'about');

        $this->get('http://primary.example.test/tr/p/about')->assertNotFound();
        $this->get('http://primary.example.test/tr')->assertNotFound();
    }
}
