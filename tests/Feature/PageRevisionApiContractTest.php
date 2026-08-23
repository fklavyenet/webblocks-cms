<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Tests\TestCase;

class PageRevisionApiContractTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  #[Test]
  public function candidate_routes_separate_read_apply_and_publish_capabilities(): void
  {
    $expected = [
      'internal-content-api.pages.versions.index' => ['internal-api.capability:content.read'],
      'internal-content-api.pages.versions.show' => ['internal-api.capability:content.read'],
      'internal-content-api.pages.versions.candidate.prepare' => ['internal-api.capability:content.apply'],
      'internal-content-api.pages.version-candidates.apply' => ['internal-api.capability:content.apply', 'internal-api.capability:content.publish'],
      'internal-content-api.pages.version-candidates.discard' => ['internal-api.capability:content.apply'],
    ];

    foreach ($expected as $route => $middleware) {
      $actual = Route::getRoutes()->getByName($route)?->gatherMiddleware() ?? [];
      foreach ($middleware as $item) {
        $this->assertContains($item, $actual);
      }
    }
  }

  #[Test]
  public function openapi_documents_preview_first_restore_flow(): void
  {
    $paths = $this->app->make(InternalApiDiscoveryController::class)->openapi()->getData(true)['paths'];

    $this->assertSame('content.read', $paths['/pages/{page}/versions']['get']['x-required-capability']);
    $this->assertSame('content.apply', $paths['/pages/{page}/versions/{revision}/candidate']['post']['x-required-capability']);
    $this->assertSame('content.apply + content.publish', $paths['/pages/{page}/version-candidates/{candidate}/apply']['post']['x-required-capability']);
    $this->assertTrue($paths['/pages/{page}/version-candidates/{candidate}/apply']['post']['x-destructive']);
  }
}
