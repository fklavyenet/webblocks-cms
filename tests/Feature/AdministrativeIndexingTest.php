<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Tests\TestCase;

class AdministrativeIndexingTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.auth', true);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function defineRoutes($router): void
  {
    Route::middleware('web')->group(function (): void {
      Route::get('/webadmin/plugin-fixture', fn () => response('private fixture'));
      Route::get('/webadmin/denied-fixture', fn () => abort(403));
      Route::get('/webadmin/robots-fixture', fn () => response('private fixture')->header('X-Robots-Tag', 'googlebot: noindex, noarchive'));
      Route::get('/webadmin/preview-fixture', fn () => response('private fixture')->header('X-Robots-Tag', 'noindex, nofollow'));
      Route::get('/public-fixture', fn () => response('public fixture'));
      Route::get('/webadministrator', fn () => response('host fixture')->header('X-Robots-Tag', 'noarchive'));
    });
  }

  #[Test]
  public function authentication_pages_and_head_responses_are_excluded_from_indexing(): void
  {
    foreach (['/webadmin/login', '/webadmin/forgot-password', '/webadmin/reset-password/synthetic-token'] as $path) {
      $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
      $this->head($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
    }
  }

  #[Test]
  public function administrative_redirects_plugin_pages_and_denials_keep_their_status(): void
  {
    $this->get('/webadmin')->assertRedirect('/webadmin/login')->assertHeader('X-Robots-Tag', 'noindex');
    $this->get('/webadmin/plugin-fixture')->assertOk()->assertSee('private fixture')->assertHeader('X-Robots-Tag', 'noindex');
    $this->get('/webadmin/denied-fixture')->assertForbidden()->assertHeader('X-Robots-Tag', 'noindex');
    $this->getJson('/webadmin/api/pages')->assertUnauthorized()->assertHeader('X-Robots-Tag', 'noindex');
    $response = $this->get('/webadmin/robots-fixture')->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
    $this->assertContains('googlebot: noindex, noarchive', $response->headers->all('X-Robots-Tag'));
    $this->get('/webadmin/preview-fixture')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
  }

  #[Test]
  public function public_and_similarly_named_host_routes_keep_their_indexing_policy(): void
  {
    $this->get('/public-fixture')->assertOk()->assertHeaderMissing('X-Robots-Tag');
    $this->get('/webadministrator')->assertOk()->assertHeader('X-Robots-Tag', 'noarchive');
  }
}
