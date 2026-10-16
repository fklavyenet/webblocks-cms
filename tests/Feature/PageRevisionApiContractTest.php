<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPageRevisionController;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageRevisionCandidate;
use WebBlocks\Cms\Tests\TestCase;

class PageRevisionApiContractTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  #[Test]
  public function revision_operations_without_a_delegated_user_fail_closed_instead_of_crashing(): void
  {
    $controller = app(InternalPageRevisionController::class);
    $request = Request::create('/webadmin/api/pages/1/versions');
    $page = new Page;
    $revision = new PageRevision;
    $candidate = new PageRevisionCandidate;
    foreach (['index' => [$page], 'show' => [$page, $revision], 'prepare' => [$page, $revision], 'apply' => [$page, $candidate], 'discard' => [$page, $candidate]] as $method => $arguments) {
      try {
        $controller->$method($request, ...$arguments);
        $this->fail('Expected authorization denial for '.$method);
      } catch (HttpException $exception) {
        $this->assertSame(403, $exception->getStatusCode());
      }
    }
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
