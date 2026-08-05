<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalSiteController;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The site form writes more than twenty fields; the API covered four narrow
 * endpoints. Three gaps mattered in practice: SEO defaults every page inherits,
 * the address contact submissions are mailed to, and which locales a site
 * publishes in -- without which a page translation for a new locale cannot be
 * saved at all.
 */
class SiteSettingsApiTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function it_writes_site_seo_defaults(): void
  {
    $site = $this->seedSite();

    $response = $this->invoke('updateSeoDefaults', $site, [
      'seo_title' => 'Studio',
      'seo_description' => 'A design studio.',
      'seo_keywords' => 'design, studio',
    ]);

    $this->assertTrue($response->getData(true)['ok']);
    $site->refresh();
    $this->assertSame('Studio', $site->seo_title);
    $this->assertSame('A design studio.', $site->seo_description);
    $this->assertSame('design, studio', $site->seo_keywords);
  }

  #[Test]
  public function seo_writes_are_partial_and_null_clears(): void
  {
    $site = $this->seedSite();
    $site->forceFill(['seo_title' => 'Kept', 'seo_description' => 'Remove me'])->save();

    $this->invoke('updateSeoDefaults', $site, ['seo_description' => null]);

    $site->refresh();
    $this->assertSame('Kept', $site->seo_title);
    $this->assertNull($site->seo_description);
  }

  #[Test]
  public function seo_rejects_fields_it_does_not_own(): void
  {
    $site = $this->seedSite();

    $response = $this->invoke('updateSeoDefaults', $site, ['seo_title' => 'ok', 'name' => 'Renamed']);

    $data = $response->getData(true);
    $this->assertFalse($data['ok']);
    $this->assertSame('name', $data['errors'][0]['path']);
    $this->assertNull($site->refresh()->seo_title);
  }

  #[Test]
  public function it_writes_the_contact_recipient(): void
  {
    $site = $this->seedSite();

    $response = $this->invoke('updateContactRecipient', $site, ['contact_recipient_email' => 'hello@example.com']);

    $this->assertTrue($response->getData(true)['ok']);
    $this->assertSame('hello@example.com', $site->refresh()->contact_recipient_email);
  }

  #[Test]
  public function it_rejects_a_malformed_contact_recipient(): void
  {
    $site = $this->seedSite();

    $response = $this->invoke('updateContactRecipient', $site, ['contact_recipient_email' => 'not-an-address']);

    $this->assertFalse($response->getData(true)['ok']);
    $this->assertNull($site->refresh()->contact_recipient_email);
  }

  #[Test]
  public function it_assigns_locales_to_a_site(): void
  {
    $site = $this->seedSite();
    $german = Locale::query()->create(['code' => 'de', 'name' => 'German', 'is_default' => false, 'is_enabled' => true]);

    $response = $this->invoke('updateLocales', $site, ['locale_ids' => [$german->id]]);

    $this->assertTrue($response->getData(true)['ok']);
    $codes = $site->fresh()->locales->pluck('code')->sort()->values()->all();
    // The default locale is kept whether or not the caller listed it.
    $this->assertSame(['de', 'en'], $codes);
  }

  #[Test]
  public function it_refuses_a_globally_disabled_locale(): void
  {
    $site = $this->seedSite();
    $french = Locale::query()->create(['code' => 'fr', 'name' => 'French', 'is_default' => false, 'is_enabled' => true]);
    $french->forceFill(['is_enabled' => false])->save();

    $response = $this->invoke('updateLocales', $site, ['locale_ids' => [$french->id]]);

    $data = $response->getData(true);
    $this->assertFalse($data['ok']);
    $this->assertStringContainsString('globally disabled', $data['errors'][0]['message']);
  }

  #[Test]
  public function it_refuses_to_detach_a_locale_that_still_has_page_translations(): void
  {
    $site = $this->seedSite();
    $german = Locale::query()->create(['code' => 'de', 'name' => 'German', 'is_default' => false, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$german->id => ['is_enabled' => true]]);

    $page = Page::query()->create(['site_id' => $site->id, 'slug' => 'about', 'status' => Page::STATUS_DRAFT]);
    $page->translations()->create(['locale_id' => $german->id, 'name' => 'Uber uns', 'slug' => 'uber-uns', 'path' => '/uber-uns']);

    $response = $this->invoke('updateLocales', $site, ['locale_ids' => [Locale::query()->where('code', 'en')->value('id')]]);

    $data = $response->getData(true);
    $this->assertFalse($data['ok']);
    $this->assertStringContainsString('still has 1 page translation', $data['errors'][0]['message']);
    $this->assertContains('de', $site->fresh()->locales->pluck('code')->all());
  }

  #[Test]
  public function the_site_payload_reads_the_new_fields_back(): void
  {
    $site = $this->seedSite();
    $site->forceFill(['seo_title' => 'Readable', 'contact_recipient_email' => 'hello@example.com'])->save();

    $payload = $this->invoke('updateSeoDefaults', $site, ['seo_keywords' => 'a, b'])->getData(true)['site'];

    $this->assertSame('Readable', $payload['seo_title']);
    $this->assertSame('a, b', $payload['seo_keywords']);
    $this->assertSame('hello@example.com', $payload['contact_recipient_email']);
  }

  #[Test]
  public function the_routes_and_openapi_schema_advertise_them(): void
  {
    $routes = (string) file_get_contents(dirname(__DIR__, 2).'/routes/admin.php');

    foreach ([
      "Route::patch('/sites/{site}/seo', [InternalSiteController::class, 'updateSeoDefaults'])->middleware('internal-api.capability:site-settings.write')",
      "Route::patch('/sites/{site}/contact-recipient', [InternalSiteController::class, 'updateContactRecipient'])->middleware('internal-api.capability:site-settings.write')",
      "Route::put('/sites/{site}/locales', [InternalSiteController::class, 'updateLocales'])->middleware('internal-api.capability:site-settings.write')",
    ] as $definition) {
      $this->assertStringContainsString($definition, $routes);
    }

    $paths = $this->app->make(InternalApiDiscoveryController::class)->openapi()->getData(true)['paths'];
    $this->assertSame('site-settings.write', $paths['/sites/{site}/seo']['patch']['x-required-capability']);
    $this->assertSame('site-settings.write', $paths['/sites/{site}/contact-recipient']['patch']['x-required-capability']);
    $this->assertSame('site-settings.write', $paths['/sites/{site}/locales']['put']['x-required-capability']);
  }

  private function seedSite(): Site
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    $locale = Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);

    return $site->fresh();
  }

  private function invoke(string $method, Site $site, array $payload)
  {
    $request = Request::create('/webadmin/api', 'PATCH', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

    return $this->app->make(InternalSiteController::class)->{$method}($request, $site);
  }
}
