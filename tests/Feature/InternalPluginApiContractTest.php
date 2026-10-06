<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Tests\TestCase;

class InternalPluginApiContractTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  #[Test]
  public function catalog_update_is_exposed_with_the_plugin_install_capability(): void
  {
    $route = Route::getRoutes()->getByName('internal-content-api.plugins.catalog.update');

    $this->assertNotNull($route);
    $this->assertContains('internal-api.capability:plugins.install', $route->gatherMiddleware());
    $this->assertSame(['POST'], $route->methods());
  }

  #[Test]
  public function discovery_documents_the_catalog_update_contract(): void
  {
    $controller = $this->app->make(InternalApiDiscoveryController::class);
    $paths = $controller->openapi()->getData(true)['paths'];

    $this->assertSame('plugins.install', $paths['/plugins/catalog/{plugin}/update']['post']['x-required-capability']);
    $this->assertArrayHasKey('422', $paths['/plugins/catalog/{plugin}/update']['post']['responses']);
  }

  #[Test]
  public function discovery_documents_compatibility_rejection_for_enable_and_setup(): void
  {
    $paths = $this->app->make(InternalApiDiscoveryController::class)->openapi()->getData(true)['paths'];
    foreach (['/plugins/{plugin}/enable', '/plugins/{plugin}/setup'] as $path) {
      $this->assertArrayHasKey('409', $paths[$path]['post']['responses']);
      $this->assertStringContainsString('incompatible', $paths[$path]['post']['responses']['409']['description']);
    }
  }
}
