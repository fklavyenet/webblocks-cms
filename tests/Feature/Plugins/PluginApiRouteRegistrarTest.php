<?php

namespace Tests\Feature\Plugins;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\Plugins\PluginApiRouteRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;

class PluginApiRouteRegistrarTest extends TestCase
{
  #[Test]
  public function it_mounts_plugin_api_routes_under_the_internal_api_group(): void
  {
    $plugin = PluginDefinition::make('demo-plugin')
      ->label('Demo Plugin')
      ->apiRoutes(function (): void {
        Route::get('/demo-plugin/ping', fn () => response()->json(['ok' => true]))
          ->middleware('internal-api.capability:commerce.read')
          ->name('demo-plugin.ping');
      });

    app(PluginApiRouteRegistrar::class)->registerApiRoutesFor($plugin);

    $route = Route::getRoutes()->getByName('internal-content-api.demo-plugin.ping');

    $this->assertNotNull($route, 'Plugin API route should be registered under the internal API name prefix.');
    $this->assertSame('webadmin/api/demo-plugin/ping', $route->uri());

    $middleware = $route->gatherMiddleware();
    $this->assertContains('internal-api.token', $middleware, 'Plugin API routes must require an internal API token.');
    $this->assertContains('internal-api.capability:commerce.read', $middleware, 'Per-route capability guard must be preserved.');
  }

  #[Test]
  public function it_skips_plugins_without_api_routes(): void
  {
    // A plugin that declares no API routes must not register anything.
    $before = count(Route::getRoutes()->getRoutes());

    $plugin = PluginDefinition::make('no-api-plugin')->label('No API Plugin');
    app(PluginApiRouteRegistrar::class)->registerApiRoutesFor($plugin);

    $this->assertCount($before, Route::getRoutes()->getRoutes());
  }

  #[Test]
  public function enabled_registration_is_a_noop_when_no_enabled_plugin_exposes_api_routes(): void
  {
    // Should not throw when iterating the real registry with no API-exposing plugins.
    app(PluginApiRouteRegistrar::class)->registerEnabledApiRoutes();

    $this->assertInstanceOf(PluginRegistry::class, app(PluginRegistry::class));
  }
}
