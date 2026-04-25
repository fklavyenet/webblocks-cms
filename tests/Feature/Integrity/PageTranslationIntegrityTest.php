<?php

namespace Tests\Feature\Integrity;

use App\Models\Locale;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\User;
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
        $page = $this->createPage($site, 'About', 'about');

        $this->assertNull($page->publicPath('tr'));
        $this->assertNull($page->publicUrl('tr'));
        $this->get('http://primary.example.test/tr/p/about')->assertNotFound();
        $this->get('http://primary.example.test/tr')->assertNotFound();
    }

    #[Test]
    public function explicit_default_locale_prefixes_do_not_resolve_public_routes(): void
    {
        $site = $this->defaultSite();
        $site->update(['domain' => 'primary.example.test']);
        $this->createPage($site, 'About', 'about');

        $this->get('http://primary.example.test/en')->assertNotFound();
        $this->get('http://primary.example.test/en/p/about')->assertNotFound();
    }

    #[Test]
    public function admin_preview_links_use_the_page_site_domain_and_only_existing_translation_routes(): void
    {
        $user = User::factory()->superAdmin()->create();
        $defaultLocale = $this->defaultLocale();
        $site = Site::query()->create([
            'name' => 'Campaign',
            'handle' => 'campaign',
            'domain' => 'campaign.example.test',
            'is_primary' => false,
        ]);
        $site->locales()->syncWithoutDetaching([$defaultLocale->id => ['is_enabled' => true]]);

        $turkish = $this->createLocale('tr');
        $german = $this->createLocale('de');
        $site->locales()->syncWithoutDetaching([
            $turkish->id => ['is_enabled' => true],
            $german->id => ['is_enabled' => true],
        ]);

        $page = $this->createPage($site, 'About', 'about');
        $page->translations()->create([
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/p/hakkinda',
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.edit', $page));

        $response->assertOk();
        $response->assertSee('https://campaign.example.test/p/about', false);
        $response->assertSee('https://campaign.example.test/tr/p/hakkinda', false);
        $response->assertDontSee('https://campaign.example.test/de/p/about', false);
    }

    #[Test]
    public function duplicate_slug_is_rejected_with_a_validation_error_before_hitting_the_database(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();
        $locale = $this->createLocale('tr');
        $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);

        $about = $this->createPage($site, 'About', 'about');
        $contact = $this->createPage($site, 'Contact', 'contact');

        $about->translations()->create([
            'locale_id' => $locale->id,
            'name' => 'Hakkinda',
            'slug' => 'ortak',
            'path' => '/p/ortak',
        ]);

        $response = $this->actingAs($user)
            ->from(route('admin.pages.edit', $contact))
            ->post(route('admin.pages.translations.store', [$contact, $locale]), [
                'name' => 'Iletisim',
                'slug' => 'ortak',
            ]);

        $response->assertRedirect(route('admin.pages.edit', $contact));
        $response->assertSessionHasErrors(['slug' => 'This slug is already used in this site for this locale.']);
    }

    #[Test]
    public function duplicate_path_is_rejected_with_a_validation_error_before_hitting_the_database(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();
        $locale = $this->createLocale('tr');
        $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);

        $home = $this->createPage($site, 'Home', 'home');
        $other = $this->createPage($site, 'About', 'about');

        $home->translations()->create([
            'locale_id' => $locale->id,
            'name' => 'Ana Sayfa',
            'slug' => 'home',
            'path' => '/',
        ]);

        $response = $this->actingAs($user)
            ->from(route('admin.pages.edit', $other))
            ->post(route('admin.pages.translations.store', [$other, $locale]), [
                'name' => 'Ana Sayfa Kopya',
                'slug' => 'home',
            ]);

        $response->assertRedirect(route('admin.pages.edit', $other));
        $response->assertSessionHasErrors(['path' => 'This path is already used in this site for this locale.']);
    }
}
