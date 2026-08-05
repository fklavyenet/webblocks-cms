<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPageTranslationController;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiPresenter;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Page-level SEO lives on the page translation row, and nothing in the API
 * could write that row after content apply created it. A page built through
 * the API had no SEO metadata, could not gain a second locale, and could not
 * be moved to a different path -- and because the read payload omitted the
 * SEO fields too, a tool could not even see what it was missing.
 */
class PageTranslationApiTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function it_writes_page_seo_metadata(): void
  {
    [$page, $translation] = $this->seedPageWithTranslation();

    $response = $this->update($page, $translation, [
      'seo_title' => 'About our studio',
      'seo_description' => 'Who we are and what we build.',
      'seo_keywords' => 'studio, design',
      'og_title' => 'About',
      'og_description' => 'Meet the team.',
    ]);

    $this->assertTrue($response->getData(true)['ok']);
    $translation->refresh();
    $this->assertSame('About our studio', $translation->seo_title);
    $this->assertSame('Who we are and what we build.', $translation->seo_description);
    $this->assertSame('studio, design', $translation->seo_keywords);
    $this->assertSame('About', $translation->og_title);
    $this->assertSame('Meet the team.', $translation->og_description);
  }

  #[Test]
  public function it_only_writes_the_fields_the_request_carries(): void
  {
    [$page, $translation] = $this->seedPageWithTranslation();
    $translation->update(['seo_title' => 'Kept', 'seo_description' => 'Also kept']);

    $this->update($page, $translation, ['seo_keywords' => 'added']);

    $translation->refresh();
    $this->assertSame('Kept', $translation->seo_title);
    $this->assertSame('Also kept', $translation->seo_description);
    $this->assertSame('added', $translation->seo_keywords);
    $this->assertSame('/about', $translation->path);
  }

  #[Test]
  public function it_clears_a_field_when_null_is_sent(): void
  {
    [$page, $translation] = $this->seedPageWithTranslation();
    $translation->update(['seo_title' => 'Remove me']);

    $this->update($page, $translation, ['seo_title' => null]);

    $this->assertNull($translation->refresh()->seo_title);
  }

  #[Test]
  public function it_renames_a_page_and_re_derives_the_slug_from_the_new_path(): void
  {
    [$page, $translation] = $this->seedPageWithTranslation();

    $response = $this->update($page, $translation, ['name' => 'Studio', 'path' => '/studio/about-us']);

    $this->assertTrue($response->getData(true)['ok']);
    $translation->refresh();
    $this->assertSame('Studio', $translation->name);
    $this->assertSame('/studio/about-us', $translation->path);
    $this->assertSame('about-us', $translation->slug);
  }

  #[Test]
  public function renaming_the_default_translation_renames_the_page_itself(): void
  {
    // Page::title and Page::slug read through to the default translation, so
    // this is the page rename the API never had -- not just a locale detail.
    [$page, $translation] = $this->seedPageWithTranslation();

    $this->update($page, $translation, ['name' => 'Studio', 'path' => '/studio']);

    $payload = $this->app->make(InternalContentApiPresenter::class)
      ->page($page->fresh(['translations.locale']));

    $this->assertSame('Studio', $payload['title']);
    $this->assertSame('studio', $payload['slug']);
    $this->assertSame('/studio', $payload['translations'][0]['path']);
  }

  #[Test]
  public function a_slug_only_change_moves_the_path_with_it(): void
  {
    [$page, $translation] = $this->seedPageWithTranslation();

    $this->update($page, $translation, ['slug' => 'the-studio']);

    $translation->refresh();
    $this->assertSame('the-studio', $translation->slug);
    $this->assertSame('/the-studio', $translation->path);
  }

  #[Test]
  public function it_rejects_a_reserved_path(): void
  {
    [$page, $translation] = $this->seedPageWithTranslation();

    $response = $this->update($page, $translation, ['path' => '/webadmin']);

    $data = $response->getData(true);
    $this->assertFalse($data['ok']);
    $this->assertSame('path', $data['errors'][0]['path']);
    $this->assertSame('/about', $translation->refresh()->path);
  }

  #[Test]
  public function it_rejects_a_path_another_translation_already_uses(): void
  {
    [$page, $translation] = $this->seedPageWithTranslation();
    $other = Page::query()->create(['site_id' => $page->site_id, 'slug' => 'contact', 'status' => Page::STATUS_DRAFT]);
    $other->translations()->create([
      'locale_id' => $translation->locale_id,
      'name' => 'Contact',
      'slug' => 'contact',
      'path' => '/contact',
    ]);

    $response = $this->update($page, $translation, ['path' => '/contact']);

    $this->assertFalse($response->getData(true)['ok']);
    $this->assertSame('/about', $translation->refresh()->path);
  }

  #[Test]
  public function it_rejects_fields_that_are_not_part_of_a_translation(): void
  {
    [$page, $translation] = $this->seedPageWithTranslation();

    $response = $this->update($page, $translation, ['status' => 'published', 'seo_title' => 'ok']);

    $data = $response->getData(true);
    $this->assertFalse($data['ok']);
    $this->assertSame('unsupported_page_translation_fields', $data['code']);
    $this->assertSame(['status'], $data['blocked_fields']);
    $this->assertNull($translation->refresh()->seo_title);
  }

  #[Test]
  public function it_adds_a_second_locale_by_code(): void
  {
    [$page] = $this->seedPageWithTranslation();
    $german = Locale::query()->create(['code' => 'de', 'name' => 'German', 'is_default' => false, 'is_enabled' => true]);
    $page->site->locales()->syncWithoutDetaching([$german->id => ['is_enabled' => true]]);

    $response = $this->store($page, 'de', ['name' => 'Über uns', 'seo_title' => 'Über das Studio']);

    $this->assertSame(201, $response->getStatusCode());
    $translation = $page->translations()->where('locale_id', $german->id)->first();
    $this->assertNotNull($translation);
    $this->assertSame('Über uns', $translation->name);
    $this->assertSame('uber-uns', $translation->slug);
    $this->assertSame('/uber-uns', $translation->path);
    $this->assertSame('Über das Studio', $translation->seo_title);
  }

  #[Test]
  public function it_refuses_a_locale_the_site_has_not_enabled(): void
  {
    [$page] = $this->seedPageWithTranslation();
    Locale::query()->create(['code' => 'fr', 'name' => 'French', 'is_default' => false, 'is_enabled' => true]);

    $response = $this->store($page, 'fr', ['name' => 'À propos']);

    $data = $response->getData(true);
    $this->assertFalse($data['ok']);
    $this->assertSame('locale', $data['errors'][0]['path']);
  }

  #[Test]
  public function it_refuses_to_create_a_translation_that_already_exists(): void
  {
    [$page] = $this->seedPageWithTranslation();

    $response = $this->store($page, 'en', ['name' => 'About again']);

    $this->assertSame('page_translation_exists', $response->getData(true)['code']);
  }

  #[Test]
  public function it_reads_seo_metadata_back_through_the_page_payload(): void
  {
    [$page, $translation] = $this->seedPageWithTranslation();
    $translation->update(['seo_title' => 'Readable', 'og_description' => 'Also readable']);

    $payload = $this->app->make(InternalPageTranslationController::class)
      ->index($page->fresh())
      ->getData(true);

    $this->assertSame('Readable', $payload['translations'][0]['seo_title']);
    $this->assertSame('Also readable', $payload['translations'][0]['og_description']);
  }

  #[Test]
  public function the_routes_and_openapi_schema_advertise_it(): void
  {
    $routes = (string) file_get_contents(dirname(__DIR__, 2).'/routes/admin.php');
    $this->assertStringContainsString(
      "Route::post('/pages/{page}/translations/{locale}', [InternalPageTranslationController::class, 'store'])->middleware('internal-api.capability:content.apply')",
      $routes
    );
    $this->assertStringContainsString(
      "Route::patch('/pages/{page}/translations/{translation}', [InternalPageTranslationController::class, 'update'])->middleware('internal-api.capability:content.apply')",
      $routes
    );

    $discovery = $this->app->make(InternalApiDiscoveryController::class);
    $links = (new \ReflectionMethod($discovery, 'links'))->invoke($discovery);
    $this->assertSame('/webadmin/api/pages/{page}/translations/{translation}', $links['page_translation_update']);

    $paths = $discovery->openapi()->getData(true)['paths'];
    $this->assertSame('content.apply', $paths['/pages/{page}/translations/{translation}']['patch']['x-required-capability']);
  }

  /**
   * @return array{0: Page, 1: PageTranslation}
   */
  private function seedPageWithTranslation(): array
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    // A default locale attaches itself to every site on create, so this only
    // guarantees the pivot for the non-default case.
    $locale = Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);

    $page = Page::query()->create([
      'site_id' => $site->id,
      'slug' => 'about',
      'status' => Page::STATUS_DRAFT,
    ]);

    $translation = $page->translations()->create([
      'locale_id' => $locale->id,
      'name' => 'About',
      'slug' => 'about',
      'path' => '/about',
    ]);

    return [$page->fresh(), $translation];
  }

  private function update(Page $page, PageTranslation $translation, array $payload)
  {
    return $this->app->make(InternalPageTranslationController::class)
      ->update($this->jsonRequest('PATCH', $payload), $page, $translation);
  }

  private function store(Page $page, string $locale, array $payload)
  {
    return $this->app->make(InternalPageTranslationController::class)
      ->store($this->jsonRequest('POST', $payload), $page, $locale);
  }

  private function jsonRequest(string $method, array $payload): Request
  {
    return Request::create('/webadmin/api', $method, [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
  }
}
