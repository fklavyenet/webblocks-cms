<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Tests\TestCase;

final class InternalExtensionCatalogApiContractTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  public function test_catalog_operations_keep_existing_installation_wide_capabilities_and_pinned_checksum_contract(): void
  {
    $paths = $this->app->make(InternalApiDiscoveryController::class)->openapi()->getData(true)['paths'];
    foreach (['themes' => 'themes.manage', 'language-packs' => 'panel-languages.manage'] as $type => $capability) {
      $route = Route::getRoutes()->getByName('internal-content-api.'.$type.'.catalog.index');
      $this->assertNotNull($route);
      $this->assertContains('internal-api.capability:'.$capability, $route->gatherMiddleware());
      foreach (['install', 'update'] as $operation) {
        $route = Route::getRoutes()->getByName('internal-content-api.'.$type.'.catalog.'.$operation);
        $this->assertNotNull($route);
        $this->assertContains('internal-api.capability:'.$capability, $route->gatherMiddleware());
        $this->assertSame(['POST'], $route->methods());
        $contract = $paths['/'.$type.'/catalog/{handle}/'.$operation]['post'];
        $this->assertSame($capability, $contract['x-required-capability']);
        $this->assertSame(['version', 'checksum'], $contract['requestBody']['content']['application/json']['schema']['required']);
        $this->assertArrayHasKey('409', $contract['responses']);
        $this->assertArrayHasKey('422', $contract['responses']);
      }
    }
  }
}
