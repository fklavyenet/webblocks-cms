<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\AdminApi\SiteDomainApiController;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteDomain;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Domain records decide which hostname resolves to which site, and the browser
 * admin keeps them behind system-level access. The API route group only ever
 * checked that a token was valid: no capability, no grant, nothing. A token
 * issued for page building could repoint or delete a production domain.
 */
class SiteDomainApiCapabilityTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);

    $app['config']->set('webblocks-cms.routes.public', true);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function every_domain_write_route_requires_its_capability(): void
  {
    $expected = [
      'sites.domains.store' => 'internal-api.capability:domains.write',
      'sites.domains.update' => 'internal-api.capability:domains.write',
      'sites.domains.primary' => 'internal-api.capability:domains.write',
      'sites.domains.destroy' => 'internal-api.capability:domains.delete',
    ];

    foreach ($expected as $name => $capability) {
      foreach (['internal-content-api.', 'admin-api.'] as $prefix) {
        $route = Route::getRoutes()->getByName($prefix.$name);

        $this->assertNotNull($route, 'Expected route '.$prefix.$name.' to be registered.');
        $this->assertContains($capability, $route->gatherMiddleware(), $prefix.$name.' must require '.$capability);
      }
    }
  }

  #[Test]
  public function domain_reads_require_at_least_content_read(): void
  {
    foreach (['sites.domains.index', 'domains.status'] as $name) {
      $route = Route::getRoutes()->getByName('internal-content-api.'.$name);

      $this->assertNotNull($route);
      $this->assertContains('internal-api.capability:content.read', $route->gatherMiddleware());
    }
  }

  #[Test]
  public function the_canonical_routes_sit_under_the_csrf_exempt_api_prefix(): void
  {
    // The CSRF exemption is registered for webadmin/api and webadmin/api/*, so
    // token clients can only POST/PUT/DELETE from under that prefix.
    $route = Route::getRoutes()->getByName('internal-content-api.sites.domains.store');

    $this->assertSame('webadmin/api/sites/{site}/domains', $route->uri());
  }

  #[Test]
  public function the_legacy_prefix_keeps_working(): void
  {
    $this->assertSame(
      'admin-api/sites/{site}/domains',
      Route::getRoutes()->getByName('admin-api.sites.domains.store')->uri(),
    );
    $this->assertTrue(Route::has('admin-api.sites.index'));
  }

  #[Test]
  public function domain_capabilities_are_opt_in_and_deletion_is_destructive(): void
  {
    $this->assertNotContains(CmsApiTokenCapabilities::DOMAINS_WRITE, CmsApiTokenCapabilities::DEFAULT);
    $this->assertNotContains(CmsApiTokenCapabilities::DOMAINS_DELETE, CmsApiTokenCapabilities::DEFAULT);
    $this->assertContains(CmsApiTokenCapabilities::DOMAINS_WRITE, CmsApiTokenCapabilities::ADVANCED);
    $this->assertContains(CmsApiTokenCapabilities::DOMAINS_DELETE, CmsApiTokenCapabilities::DESTRUCTIVE);
    $this->assertContains(CmsApiTokenCapabilities::DOMAINS_WRITE, CmsApiTokenCapabilities::ALL);
    $this->assertContains(CmsApiTokenCapabilities::DOMAINS_DELETE, CmsApiTokenCapabilities::ALL);
  }

  #[Test]
  public function it_promotes_a_domain_to_primary(): void
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    $first = SiteDomain::query()->create(['site_id' => $site->id, 'domain' => 'one.example', 'is_primary' => true, 'status' => SiteDomain::STATUS_ACTIVE]);
    $second = SiteDomain::query()->create(['site_id' => $site->id, 'domain' => 'two.example', 'is_primary' => false, 'status' => SiteDomain::STATUS_ACTIVE]);

    $response = $this->app->make(SiteDomainApiController::class)->setPrimaryDomain($site, $second);

    $this->assertTrue($response->getData(true)['domain']['is_primary']);
    $this->assertFalse((bool) $first->refresh()->is_primary);
    $this->assertTrue((bool) $second->refresh()->is_primary);
  }
}
