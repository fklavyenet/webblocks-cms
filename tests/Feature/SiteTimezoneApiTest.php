<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalSiteController;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The admin edit form could always set a site's own timezone (blank falls back to
 * the install-wide system timezone). The Internal Content API had no way to write
 * it — only ever to read whatever it resolved to in rendered content — the same
 * gap the page layout and Shared Slot detachment endpoints closed for their own
 * fields: writable from the admin session only, with no way back out through the
 * API that manages everything else about a site.
 */
class SiteTimezoneApiTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  private function controller(): InternalSiteController
  {
    return app(InternalSiteController::class);
  }

  private function site(): Site
  {
    return Site::create(['name' => 'Play', 'handle' => 'play', 'is_primary' => true]);
  }

  #[Test]
  public function it_stores_a_valid_iana_timezone(): void
  {
    $site = $this->site();

    $response = $this->controller()->updateTimezone(
      Request::create('/', 'PATCH', ['timezone' => 'Europe/Berlin']),
      $site,
    );

    $this->assertSame(200, $response->getStatusCode());
    $payload = $response->getData(true);
    $this->assertTrue($payload['ok']);
    $this->assertSame('site_timezone', $payload['writes'][0]['type']);
    $this->assertSame('Europe/Berlin', $site->fresh()->timezone);
    $this->assertSame('Europe/Berlin', $site->fresh()->resolvedTimezone());
  }

  #[Test]
  public function an_empty_value_clears_it_back_to_the_system_timezone(): void
  {
    $site = $this->site();
    $site->forceFill(['timezone' => 'Europe/Berlin'])->save();

    $response = $this->controller()->updateTimezone(
      Request::create('/', 'PATCH', ['timezone' => '   ']),
      $site,
    );

    $this->assertSame(200, $response->getStatusCode());
    $this->assertNull($site->fresh()->timezone);
  }

  #[Test]
  public function it_rejects_a_value_that_is_not_a_real_timezone(): void
  {
    $site = $this->site();

    $response = $this->controller()->updateTimezone(
      Request::create('/', 'PATCH', ['timezone' => 'Not/A/Zone']),
      $site,
    );

    $this->assertSame(422, $response->getStatusCode());
    $payload = $response->getData(true);
    $this->assertFalse($payload['ok']);
    $this->assertSame('timezone', $payload['errors'][0]['path']);
    $this->assertNull($site->fresh()->timezone);
  }

  #[Test]
  public function the_route_and_discovery_advertise_it(): void
  {
    $routes = (string) file_get_contents(dirname(__DIR__, 2).'/routes/admin.php');
    $this->assertStringContainsString(
      "Route::patch('/sites/{site}/timezone', [InternalSiteController::class, 'updateTimezone'])->middleware('internal-api.capability:site-settings.write')",
      $routes
    );

    $discovery = $this->app->make(InternalApiDiscoveryController::class);
    $links = (new \ReflectionMethod($discovery, 'links'))->invoke($discovery);
    $this->assertSame('/webadmin/api/sites/{site}/timezone', $links['site_timezone']);

    $paths = $discovery->openapi()->getData(true)['paths'];
    $this->assertSame('site-settings.write', $paths['/sites/{site}/timezone']['patch']['x-required-capability']);
  }
}
