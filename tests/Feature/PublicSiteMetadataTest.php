<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use App\Support\Blocks\BlockTranslationWriter;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicSiteMetadataTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function public_layout_renders_site_level_meta_description_and_keywords_for_the_resolved_site(): void
    {
        [$site] = $this->seedPublicSite();

        $site->update([
            'display_name' => 'Marketing Site',
            'seo_title' => 'Marketing Default Title',
            'seo_description' => 'Default site description.',
            'seo_keywords' => 'alpha,beta,gamma',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<meta name="description" content="Default site description.">', false);
        $response->assertSee('<meta name="keywords" content="alpha,beta,gamma">', false);
        $response->assertSee('<meta property="og:title" content="Home">', false);
        $response->assertSee('<meta property="og:description" content="Default site description.">', false);
    }

    #[Test]
    public function public_layout_renders_favicon_link_when_the_resolved_site_has_a_favicon_asset(): void
    {
        [$site, $locale, $slotType] = $this->seedPublicSite();
        $favicon = $this->imageAsset('favicons/site-icon.png', 'site-icon.png');

        $site->update([
            'favicon_asset_id' => $favicon->id,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('href="'.$favicon->url().'"', false);
    }

    #[Test]
    public function public_metadata_is_scoped_to_the_host_resolved_site(): void
    {
        [$primarySite, $locale, $slotType, $headerType] = $this->seedPublicSite();
        $primarySite->update([
            'display_name' => 'Primary Site',
            'seo_title' => 'Primary Meta Title',
            'seo_description' => 'Primary description.',
            'seo_keywords' => 'primary',
        ]);

        $campaignSite = Site::query()->create([
            'name' => 'Campaign Admin Name',
            'display_name' => 'Campaign Public Name',
            'handle' => 'campaign',
            'domain' => 'campaign.example.test',
            'is_primary' => false,
            'seo_title' => 'Campaign Meta Title',
            'seo_description' => 'Campaign description.',
            'seo_keywords' => 'campaign',
        ]);
        $campaignSite->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
        $this->createPage($campaignSite, $locale, $slotType, $headerType, 'Campaign Home', 'campaign-home', '/p/campaign-home');

        $response = $this->get('http://campaign.example.test/p/campaign-home');

        $response->assertOk();
        $response->assertSee('<title>Campaign Home</title>', false);
        $response->assertSee('<meta name="description" content="Campaign description.">', false);
        $response->assertSee('<meta name="keywords" content="campaign">', false);
        $response->assertSee('<meta property="og:title" content="Campaign Home">', false);
        $response->assertDontSee('Primary description.');
        $response->assertDontSee('Primary Meta Title');
    }

    #[Test]
    public function page_translation_seo_overrides_take_precedence_over_site_defaults(): void
    {
        [$site, $locale] = $this->seedPublicSite();
        $page = Page::query()->firstOrFail();
        $ogImage = $this->imageAsset('social/page-og.png', 'page-og.png');

        $site->update([
            'display_name' => 'Marketing Site',
            'seo_title' => 'Site Default Title',
            'seo_description' => 'Site default description.',
            'seo_keywords' => 'site,keywords',
        ]);

        $page->translationForLocale($locale)->update([
            'seo_title' => 'Page SEO Title',
            'seo_description' => 'Page SEO Description',
            'seo_keywords' => 'page,keywords',
            'og_title' => 'Page OG Title',
            'og_description' => 'Page OG Description',
            'og_image_asset_id' => $ogImage->id,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<title>Page SEO Title</title>', false);
        $response->assertSee('<meta name="description" content="Page SEO Description">', false);
        $response->assertSee('<meta name="keywords" content="page,keywords">', false);
        $response->assertSee('<meta property="og:title" content="Page OG Title">', false);
        $response->assertSee('<meta property="og:description" content="Page OG Description">', false);
        $response->assertSee('<meta property="og:image" content="'.$ogImage->url().'">', false);
        $response->assertDontSee('Site Default Title');
        $response->assertDontSee('Site default description.');
    }

    #[Test]
    public function localized_routes_render_locale_specific_page_seo_overrides(): void
    {
        [$site, $locale, $slotType, $headerType] = $this->seedPublicSite();
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
        $page = Page::query()->firstOrFail();
        $trOgImage = $this->imageAsset('social/page-og-tr.png', 'page-og-tr.png');

        $site->update([
            'domain' => 'primary.example.test',
            'seo_title' => 'Site Default Title',
            'seo_description' => 'Site default description.',
        ]);

        $page->translations()->create([
            'site_id' => $site->id,
            'locale_id' => $turkish->id,
            'name' => 'Ana Sayfa',
            'slug' => 'anasayfa',
            'path' => '/p/anasayfa',
            'seo_title' => 'TR SEO Baslik',
            'seo_description' => 'TR SEO Aciklama',
            'seo_keywords' => 'tr,anahtar',
            'og_title' => 'TR OG Baslik',
            'og_description' => 'TR OG Aciklama',
            'og_image_asset_id' => $trOgImage->id,
        ]);

        $response = $this->get('http://primary.example.test/tr/p/anasayfa');

        $response->assertOk();
        $response->assertSee('<title>TR SEO Baslik</title>', false);
        $response->assertSee('<meta name="description" content="TR SEO Aciklama">', false);
        $response->assertSee('<meta name="keywords" content="tr,anahtar">', false);
        $response->assertSee('<meta property="og:title" content="TR OG Baslik">', false);
        $response->assertSee('<meta property="og:description" content="TR OG Aciklama">', false);
        $response->assertSee('<meta property="og:image" content="'.$trOgImage->url().'">', false);
    }

    #[Test]
    public function blank_page_seo_fields_fall_back_to_page_title_and_site_defaults_safely(): void
    {
        [$site, $locale] = $this->seedPublicSite();
        $page = Page::query()->firstOrFail();

        $site->update([
            'display_name' => 'Marketing Site',
            'seo_title' => 'Site Default Title',
            'seo_description' => 'Site default description.',
            'seo_keywords' => 'site,keywords',
        ]);

        $page->translationForLocale($locale)->update([
            'seo_title' => null,
            'seo_description' => null,
            'seo_keywords' => null,
            'og_title' => null,
            'og_description' => null,
            'og_image_asset_id' => null,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<title>Home</title>', false);
        $response->assertSee('<meta name="description" content="Site default description.">', false);
        $response->assertSee('<meta name="keywords" content="site,keywords">', false);
        $response->assertSee('<meta property="og:title" content="Home">', false);
        $response->assertSee('<meta property="og:description" content="Site default description.">', false);
        $response->assertDontSee('<meta property="og:image"', false);
    }

    #[Test]
    public function metadata_renders_without_description_or_keywords_when_no_site_or_page_values_exist(): void
    {
        $this->seedPublicSite();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<title>Home</title>', false);
        $response->assertDontSee('<meta name="description"', false);
        $response->assertDontSee('<meta name="keywords"', false);
        $response->assertSee('<meta property="og:title" content="Home">', false);
        $response->assertDontSee('<meta property="og:description"', false);
    }

    private function seedPublicSite(): array
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);

        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $locale = Locale::query()->where('is_default', true)->firstOrFail();
        $slotType = SlotType::query()->updateOrCreate(
            ['slug' => 'main'],
            ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );
        $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();

        $this->createPage($site, $locale, $slotType, $headerType, 'Home', 'home', '/p/home');

        return [$site, $locale, $slotType, $headerType];
    }

    private function createPage(Site $site, Locale $locale, SlotType $slotType, BlockType $headerType, string $title, string $slug, string $path): Page
    {
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => $title,
            'slug' => $slug,
            'status' => 'published',
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => $locale->id],
            ['site_id' => $site->id, 'name' => $title, 'slug' => $slug, 'path' => $path],
        );

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'sort_order' => 0,
        ]);

        $block = $page->blocks()->create([
            'type' => 'header',
            'block_type_id' => $headerType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'title' => $title,
            'variant' => 'h1',
            'is_system' => false,
        ]);

        $block->textTranslations()->create(['locale_id' => $locale->id, 'title' => $title]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        return $page;
    }

    private function imageAsset(string $path, string $filename): Asset
    {
        return Asset::query()->create([
            'disk' => 'public',
            'path' => $path,
            'filename' => $filename,
            'original_name' => $filename,
            'extension' => 'png',
            'mime_type' => 'image/png',
            'size' => 1024,
            'kind' => Asset::KIND_IMAGE,
            'visibility' => 'public',
            'width' => 64,
            'height' => 64,
        ]);
    }
}
