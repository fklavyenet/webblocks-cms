<?php

namespace Tests\Feature\Integrity;

use App\Models\Locale;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Support\Sites\SiteCloneOptions;
use App\Support\Sites\SiteCloneService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SiteLocaleIntegrityTest extends TestCase
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

    private function createLocale(string $code, bool $enabled = true): Locale
    {
        return Locale::query()->create([
            'code' => $code,
            'name' => strtoupper($code),
            'is_default' => false,
            'is_enabled' => $enabled,
        ]);
    }

    #[Test]
    public function cannot_use_a_locale_for_page_translation_when_it_is_not_enabled_for_the_site(): void
    {
        $site = $this->defaultSite();
        $turkish = $this->createLocale('tr');
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => Page::STATUS_PUBLISHED,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Page translation locale must be enabled for the page site.');

        $page->translations()->create([
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/p/hakkinda',
        ]);
    }

    #[Test]
    public function cannot_use_a_disabled_locale_for_page_translation(): void
    {
        $site = $this->defaultSite();
        $turkish = $this->createLocale('tr');
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
        DB::table('site_locales')
            ->where('site_id', $site->id)
            ->where('locale_id', $turkish->id)
            ->update(['is_enabled' => false]);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => Page::STATUS_PUBLISHED,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Page translation locale must be enabled for the page site.');

        $page->translations()->create([
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/p/hakkinda',
        ]);
    }

    #[Test]
    public function enabling_and_disabling_a_locale_changes_page_availability_for_that_site(): void
    {
        $site = $this->defaultSite();
        $site->update(['domain' => 'primary.example.test']);
        $turkish = $this->createLocale('tr');
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => Page::STATUS_PUBLISHED,
        ]);

        $page->translations()->create([
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/p/hakkinda',
        ]);

        $this->get('http://primary.example.test/tr/p/hakkinda')->assertOk()->assertSee('Hakkinda');

        DB::table('site_locales')
            ->where('site_id', $site->id)
            ->where('locale_id', $turkish->id)
            ->update(['is_enabled' => false]);

        $this->assertNull($page->fresh()->publicPath('tr'));
        $this->get('http://primary.example.test/tr/p/hakkinda')->assertNotFound();

        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        $this->assertSame('/tr/p/hakkinda', $page->fresh()->publicPath('tr'));
        $this->get('http://primary.example.test/tr/p/hakkinda')->assertOk()->assertSee('Hakkinda');
    }

    #[Test]
    public function cloning_a_site_preserves_only_that_sites_locale_assignments(): void
    {
        $sourceSite = Site::query()->create([
            'name' => 'Source',
            'handle' => 'source',
            'domain' => 'source.example.test',
            'is_primary' => false,
        ]);
        $otherSite = Site::query()->create([
            'name' => 'Other',
            'handle' => 'other',
            'domain' => 'other.example.test',
            'is_primary' => false,
        ]);

        $defaultLocale = $this->defaultLocale();
        $turkish = $this->createLocale('tr');
        $french = $this->createLocale('fr');

        $sourceSite->locales()->sync([
            $defaultLocale->id => ['is_enabled' => true],
            $turkish->id => ['is_enabled' => true],
        ]);
        $otherSite->locales()->syncWithoutDetaching([
            $defaultLocale->id => ['is_enabled' => true],
            $french->id => ['is_enabled' => true],
        ]);

        $result = app(SiteCloneService::class)->clone(
            $sourceSite->id,
            'source-clone',
            SiteCloneOptions::fromArray([
                'target_name' => 'Source Clone',
                'target_handle' => 'source-clone',
            ]),
        );

        $clonedSite = $result->targetSite;
        $enabledCodes = $clonedSite->fresh()->enabledLocales()->orderBy('code')->pluck('code')->all();

        $this->assertSame(['en', 'tr'], $enabledCodes);
        $this->assertFalse($clonedSite->hasEnabledLocale($french));
    }

    #[Test]
    public function raw_inserts_with_an_invalid_page_translation_locale_id_are_rejected_by_database_integrity(): void
    {
        $page = Page::query()->create([
            'site_id' => $this->defaultSite()->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => Page::STATUS_PUBLISHED,
        ]);

        $this->expectException(QueryException::class);

        DB::table('page_translations')->insert([
            'page_id' => $page->id,
            'site_id' => $page->site_id,
            'locale_id' => 999999,
            'name' => 'Broken',
            'slug' => 'broken',
            'path' => '/p/broken',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
