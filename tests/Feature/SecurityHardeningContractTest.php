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
    $this->assertStringContainsString("object-src 'none'", $controller);
    $this->assertStringContainsString("form-action 'none'", $controller);
    $this->assertStringContainsString("'Referrer-Policy' => 'no-referrer'", $controller);
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
  public function navigation_sorting_uses_the_package_owned_script(): void
  {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/navigation/index.blade.php');

    $this->assertIsString($view);
    $this->assertStringContainsString("asset('cms/js/vendor/sortablejs-1.15.6.min.js')", $view);
    $this->assertStringNotContainsString('cdn.jsdelivr.net/npm/sortablejs', $view);
    $this->assertFileExists(dirname(__DIR__, 2).'/public/cms/js/vendor/sortablejs-1.15.6.min.js');
    $this->assertFileExists(dirname(__DIR__, 2).'/public/cms/js/vendor/sortablejs-LICENSE.txt');
  }
}
