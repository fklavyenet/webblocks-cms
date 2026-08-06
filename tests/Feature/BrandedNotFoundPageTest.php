<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Tests\TestCase;

/**
 * A 404 used to fall through to Laravel's plain default page, unbranded and
 * English-only. The package now registers a renderable for
 * NotFoundHttpException that serves a branded, locale-aware 404 for public
 * HTML requests — while JSON requests keep their JSON 404 and a host app
 * that ships its own resources/views/errors/404.blade.php keeps winning.
 */
class BrandedNotFoundPageTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function an_unknown_public_path_renders_the_branded_404(): void
  {
    $this->seedSite();

    $response = $this->get('/no-such-page');

    $response->assertNotFound();
    $response->assertSee('wb-error-code', escape: false);
    $response->assertSee('data-wb-public-theme', escape: false);
    $response->assertSee('This page does not exist');
    $response->assertSee('noindex, nofollow', escape: false);
    // The brand line names the site and links home.
    $response->assertSee('class="wb-error-brand-link">Test</a>', escape: false);
  }

  #[Test]
  public function a_locale_prefixed_path_renders_the_404_in_that_locale(): void
  {
    $this->seedSite();

    $response = $this->get('/tr/boyle-bir-sayfa-yok');

    $response->assertNotFound();
    $response->assertSee('Bu sayfa mevcut değil');
    $response->assertSee('lang="tr"', escape: false);
    // Both the brand line and the CTA point at the Turkish home.
    $response->assertSee('<a href="/tr" class="wb-error-brand-link">', escape: false);
    $response->assertSee('href="/tr" class="wb-btn', escape: false);
  }

  #[Test]
  public function a_json_request_keeps_its_json_404(): void
  {
    $this->seedSite();

    $response = $this->getJson('/no-such-endpoint');

    $response->assertNotFound();
    $response->assertHeader('Content-Type', 'application/json');
    $this->assertStringNotContainsString('wb-error-code', $response->getContent());
  }

  #[Test]
  public function a_host_app_error_view_wins_over_the_package_404(): void
  {
    $this->seedSite();
    $appErrorsDir = resource_path('views/errors');
    File::ensureDirectoryExists($appErrorsDir);
    file_put_contents($appErrorsDir.'/404.blade.php', 'HOST APP OWNS THIS 404');

    try {
      $response = $this->get('/no-such-page');

      $response->assertNotFound();
      $response->assertSee('HOST APP OWNS THIS 404');
      $this->assertStringNotContainsString('wb-error-code', $response->getContent());
    } finally {
      File::delete($appErrorsDir.'/404.blade.php');
    }
  }

  private function seedSite(): void
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    $english = Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $turkish = Locale::query()->firstOrCreate(['code' => 'tr'], ['name' => 'Türkçe', 'is_default' => false, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$english->id => ['is_enabled' => true], $turkish->id => ['is_enabled' => true]]);
  }
}
