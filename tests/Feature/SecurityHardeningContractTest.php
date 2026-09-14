<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Tests\TestCase;

class SecurityHardeningContractTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);

    $app['config']->set('webblocks-cms.routes.public', true);
  }

  #[Test]
  public function legacy_internal_api_routes_keep_the_canonical_rate_limit(): void
  {
    $route = Route::getRoutes()->getByName('admin-api.sites.domains.store');

    $this->assertNotNull($route);
    $this->assertContains('throttle:internal-content-api', $route->gatherMiddleware());
  }

  #[Test]
  public function embedded_application_frames_do_not_receive_same_origin_authority(): void
  {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/pages/partials/blocks/application.blade.php');

    $this->assertIsString($view);
    $this->assertStringContainsString('sandbox="allow-scripts"', $view);
    $this->assertStringNotContainsString('allow-same-origin', $view);
  }

  #[Test]
  public function embedded_application_entries_ship_restrictive_browser_headers(): void
  {
    $controller = file_get_contents(dirname(__DIR__, 2).'/src/Http/Controllers/Public/EmbeddedApplicationEntryController.php');

    $this->assertIsString($controller);
    $this->assertStringContainsString("'Content-Security-Policy'", $controller);
    $this->assertStringContainsString('getSchemeAndHttpHost', $controller);
    $this->assertStringContainsString("default-src 'none'", $controller);
    $this->assertStringContainsString('base-uri {$origin}', $controller);
    $this->assertStringNotContainsString("base-uri 'none'", $controller);
    $this->assertStringContainsString("object-src 'none'", $controller);
    $this->assertStringContainsString("form-action 'none'", $controller);
    $this->assertStringContainsString("'Referrer-Policy' => 'no-referrer'", $controller);
  }

  #[Test]
  public function packaged_application_assets_keep_the_opaque_sandbox_and_receive_public_cors_headers(): void
  {
    $controller = file_get_contents(dirname(__DIR__, 2).'/src/Http/Controllers/Public/EmbeddedApplicationPackageController.php');
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/public.php');

    $this->assertStringContainsString("'Access-Control-Allow-Origin' => '*'", $controller);
    $this->assertStringContainsString("'Cross-Origin-Resource-Policy' => 'cross-origin'", $controller);
    $this->assertStringContainsString("'Cache-Control' => 'public, max-age=31536000, immutable'", $controller);
    $this->assertStringContainsString('/webblocks-applications/{application}/{version}/{path}', $routes);
  }

  #[Test]
  public function remote_media_fetches_pin_the_validated_dns_address(): void
  {
    $fetcher = file_get_contents(dirname(__DIR__, 2).'/src/Support/Media/RemoteMediaFetcher.php');

    $this->assertIsString($fetcher);
    $this->assertStringContainsString('CURLOPT_RESOLVE', $fetcher);
    $this->assertStringContainsString('curlResolveEntry($currentUrl, $pinnedAddress)', $fetcher);
  }

  #[Test]
  public function navigation_sorting_uses_the_cms_native_pointer_and_keyboard_module(): void
  {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/navigation/index.blade.php');
    $script = file_get_contents(dirname(__DIR__, 2).'/public/cms/js/admin/navigation-tree.js');

    $this->assertIsString($view);
    $this->assertIsString($script);
    $this->assertStringContainsString("'cms/js/admin/navigation-tree.js'", $view);
    $this->assertStringContainsString("root.addEventListener('pointerdown'", $script);
    $this->assertStringContainsString("root.addEventListener('keydown'", $script);
    $this->assertStringContainsString("['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight']", $script);
    $this->assertStringNotContainsString('Sortable', $view.$script);
    $this->assertFileDoesNotExist(dirname(__DIR__, 2).'/public/cms/js/vendor/sortablejs-1.15.6.min.js');
  }
}
