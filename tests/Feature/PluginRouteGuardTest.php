<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Http\Middleware\GuardPluginSetup;
use WebBlocks\Cms\Http\Middleware\UseCmsAuthenticationRedirect;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginPermission;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRouteRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginSettingsDefinition;

class PluginRouteGuardTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function plugin_system_preserves_admin_and_static_asset_route_boundaries(): void
  {
    $this->assertNotNull(Route::getRoutes()->getByName('admin.dashboard'));
    $this->assertNotNull(Route::getRoutes()->getByName('admin.system.plugins.index'));
    $this->assertNotNull(Route::getRoutes()->getByName('admin.plugins.catalog.index'));
    $this->assertSame('webadmin', Route::getRoutes()->getByName('admin.dashboard')?->uri());
    $this->assertSame('webadmin/system/plugins', Route::getRoutes()->getByName('admin.system.plugins.index')?->uri());
    $this->assertSame('webadmin/plugins/catalog', Route::getRoutes()->getByName('admin.plugins.catalog.index')?->uri());

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

  #[Test]
  public function enabled_plugin_admin_routes_register_under_default_plugin_namespace(): void
  {
    $registry = new PluginRegistry([
      'webblocks-ui-manager' => true,
      'disabled-plugin' => false,
    ]);

    $registry->register(
      PluginDefinition::make('webblocks-ui-manager')
        ->label('WebBlocks UI Manager')
        ->permissions([
          PluginPermission::make('webblocks-ui-manager.manage'),
        ])
        ->settings(PluginSettingsDefinition::make()->label('Release Settings'))
        ->adminRoutes(function (): void {
          Route::get('/releases', fn () => 'enabled plugin route')
            ->name('releases.index');
        })
    );

    $registry->register(
      PluginDefinition::make('disabled-plugin')
        ->label('Disabled Plugin')
        ->settings(PluginSettingsDefinition::make())
        ->adminRoutes(function (): void {
          Route::get('/tools', fn () => 'disabled plugin route')
            ->name('tools.index');
        })
    );

    (new PluginRouteRegistrar($registry))->registerEnabledAdminRoutes();
    Route::getRoutes()->refreshNameLookups();

    $enabledRoute = Route::getRoutes()->getByName('webblocks.plugins.webblocks_ui_manager.releases.index');
    $settingsRoute = Route::getRoutes()->getByName('webblocks.plugins.webblocks_ui_manager.settings.edit');

    $this->assertNotNull($enabledRoute);
    $this->assertSame('webadmin/plugins/webblocks-ui-manager/releases', $enabledRoute?->uri());
    $this->assertSame([
      'web',
      'install.required',
      UseCmsAuthenticationRedirect::class,
      'admin.access',
      GuardPluginSetup::class.':webblocks-ui-manager',
      'plugin.permission:webblocks-ui-manager.view',
    ], $enabledRoute?->gatherMiddleware());
    $this->assertNotNull($settingsRoute);
    $this->assertSame('webadmin/plugins/webblocks-ui-manager/settings', $settingsRoute?->uri());
    $this->assertSame([
      'web',
      'install.required',
      UseCmsAuthenticationRedirect::class,
      'admin.access',
      GuardPluginSetup::class.':webblocks-ui-manager',
      'plugin.permission:webblocks-ui-manager.manage',
    ], $settingsRoute?->gatherMiddleware());
    $this->assertNull(Route::getRoutes()->getByName('webblocks.plugins.disabled_plugin.tools.index'));
    $this->assertNull(Route::getRoutes()->getByName('webblocks.plugins.disabled_plugin.settings.edit'));

    $pluginRoutes = collect(Route::getRoutes()->getRoutes())
      ->filter(fn ($route): bool => str_starts_with($route->uri(), 'webadmin/plugins/'))
      ->map(fn ($route): string => $route->uri())
      ->values()
      ->all();

    $this->assertContains('webadmin/plugins/webblocks-ui-manager/releases', $pluginRoutes);
    $this->assertNotContains('webadmin/plugins/disabled-plugin/tools', $pluginRoutes);
  }

  #[Test]
  public function plugin_admin_route_files_cannot_replace_the_cms_admin_middleware_stack(): void
  {
    $registry = new PluginRegistry([
      'webblocks-redirect-manager' => true,
    ]);

    $registry->register(
      PluginDefinition::make('webblocks-redirect-manager')
        ->label('WebBlocks Redirect Manager')
        ->permissions([
          PluginPermission::make('webblocks-redirect-manager.view'),
        ])
        ->adminRoutes(function (): void {
          Route::get('/redirects', fn () => 'redirect manager user:'.request()->user()?->id)
            ->middleware(GuardPluginSetup::class.':webblocks-redirect-manager')
            ->middleware('plugin.permission:webblocks-redirect-manager.view')
            ->name('redirects.index');
        })
    );
    $this->app->instance(PluginRegistry::class, $registry);

    (new PluginRouteRegistrar($registry))->registerEnabledAdminRoutes();
    Route::getRoutes()->refreshNameLookups();

    $route = Route::getRoutes()->getByName('webblocks.plugins.webblocks_redirect_manager.redirects.index');

    $this->assertNotNull($route);
    $this->assertSame('webadmin/plugins/webblocks-redirect-manager/redirects', $route?->uri());
    $this->assertSame([
      'web',
      'install.required',
      UseCmsAuthenticationRedirect::class,
      'admin.access',
      GuardPluginSetup::class.':webblocks-redirect-manager',
      'plugin.permission:webblocks-redirect-manager.view',
    ], $route?->gatherMiddleware());

    $user = User::factory()->superAdmin()->create();

    $this->get('/webadmin/plugins/webblocks-redirect-manager/redirects')
      ->assertRedirect(route('webblocks.auth.login'));

    $this->actingAs($user)
      ->get('/webadmin/plugins/webblocks-redirect-manager/redirects')
      ->assertOk()
      ->assertSeeText('redirect manager user:'.$user->id);
  }
}
