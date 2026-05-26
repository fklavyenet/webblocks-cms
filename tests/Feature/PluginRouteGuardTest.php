<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;

class PluginRouteGuardTest extends TestCase
{
  #[Test]
  public function plugin_system_preserves_admin_and_static_asset_route_boundaries(): void
  {
    $this->assertNotNull(Route::getRoutes()->getByName('admin.dashboard'));
    $this->assertNotNull(Route::getRoutes()->getByName('admin.system.plugins.index'));
    $this->assertSame('webadmin', Route::getRoutes()->getByName('admin.dashboard')?->uri());
    $this->assertSame('webadmin/system/plugins', Route::getRoutes()->getByName('admin.system.plugins.index')?->uri());

    $adminRoutes = collect(Route::getRoutes()->getRoutes())
      ->filter(fn ($route): bool => $route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/'))
      ->map(fn ($route): string => $route->uri())
      ->values()
      ->all();

    $cmsRoutes = collect(Route::getRoutes()->getRoutes())
      ->filter(fn ($route): bool => $route->uri() === 'cms' || str_starts_with($route->uri(), 'cms/'))
      ->map(fn ($route): string => $route->uri())
      ->values()
      ->all();

    $this->assertSame([], $adminRoutes);
    $this->assertSame([], $cmsRoutes);
  }

  #[Test]
  public function default_plugin_route_namespace_contract_is_stable_without_dynamic_routes(): void
  {
    $plugin = PluginDefinition::make('webblocks-ui-manager')->label('WebBlocks UI Manager');

    $this->assertSame('/webadmin/plugins/webblocks-ui-manager', $plugin->adminRoutePrefix());
    $this->assertSame('webblocks.plugins.webblocks_ui_manager', $plugin->routeNamePrefix());
  }
}
