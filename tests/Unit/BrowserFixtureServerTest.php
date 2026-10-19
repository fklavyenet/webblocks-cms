<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\Support\BrowserFixtureServer;

class BrowserFixtureServerTest extends TestCase
{
  public function test_existing_file_urls_are_not_treated_as_the_fixture_index(): void
  {
    $request = Request::create('http://127.0.0.1/composer.json', server: [
      'SCRIPT_FILENAME' => dirname(__DIR__, 2).'/composer.json',
      'SCRIPT_NAME' => '/composer.json', 'PHP_SELF' => '/composer.json',
    ]);
    $response = BrowserFixtureServer::forRequest('parent', $request);
    $this->assertSame(404, $response->getStatusCode());
    $this->assertSame('Not found.', $response->getContent());
    $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    $this->assertSame(403, BrowserFixtureServer::forRequest('parent', Request::create('http://example.test/'))->getStatusCode());
  }

  public function test_each_fixture_serves_its_real_assets_and_the_shared_browser_controls(): void
  {
    foreach (['parent' => null, 'asset-picker' => 'asset-picker', 'rich-text' => 'rich-text-editor'] as $scenario => $script) {
      $paths = ['/cms/webblocks-ui/'.WebBlocks::UI_VERSION.'/webblocks-ui.js', '/cms/css/admin.css', '/fixture.js', '/fixture.css'];
      if ($script !== null) {
        $paths[] = '/cms/js/admin/'.$script.'.js';
      }
      foreach ($paths as $path) {
        $response = BrowserFixtureServer::response($scenario, 'GET', $path);
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getFile()->getContent());
        $this->assertSame(str_ends_with($path, '.css') ? 'text/css; charset=UTF-8' : 'application/javascript; charset=UTF-8', $response->headers->get('Content-Type'));
      }
    }
  }

  public function test_routers_are_read_only_and_do_not_expose_repository_or_unrelated_files(): void
  {
    foreach (['parent', 'asset-picker', 'rich-text'] as $scenario) {
      foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
        $response = BrowserFixtureServer::response($scenario, $method, '/');
        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET, HEAD', $response->headers->get('Allow'));
      }
      foreach (['/.env', '/composer.json', '/tests/Feature/AssetPickerViewTest.php', '/cms/../../composer.json', '/cms/js/admin/core.js', '/missing.css'] as $path) {
        $this->assertSame(404, BrowserFixtureServer::response($scenario, 'GET', $path)->getStatusCode());
      }
    }
    $this->assertSame(404, BrowserFixtureServer::response('unknown', 'GET', '/')->getStatusCode());
  }

  public function test_synthetic_images_are_bounded_and_only_available_to_the_picker(): void
  {
    foreach ([1, 250] as $id) {
      $response = BrowserFixtureServer::response('asset-picker', 'GET', '/storage/media/image-'.$id.'.jpg');
      $this->assertSame(200, $response->getStatusCode());
      $this->assertSame('image/svg+xml', $response->headers->get('Content-Type'));
    }
    foreach (['/storage/media/image-0.jpg', '/storage/media/image-251.jpg', '/storage/other.jpg'] as $path) {
      $this->assertSame(404, BrowserFixtureServer::response('asset-picker', 'GET', $path)->getStatusCode());
    }
    foreach (['parent', 'rich-text'] as $scenario) {
      $this->assertSame(404, BrowserFixtureServer::response($scenario, 'GET', '/storage/media/image-1.jpg')->getStatusCode());
    }
  }
}
