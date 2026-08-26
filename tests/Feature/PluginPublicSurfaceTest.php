<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\Plugins\PluginBlockCatalog;
use WebBlocks\Cms\Support\Plugins\PluginBlockTypeDefinition;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginException;
use WebBlocks\Cms\Support\Plugins\PluginMenuItem;
use WebBlocks\Cms\Support\Plugins\PluginPublicRouteRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginSettingsDefinition;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Covers the two core extension points a plugin needs before it can own a
 * visitor-facing surface: declaring public routes, and declaring the views its
 * block types render through.
 */
class PluginPublicSurfaceTest extends TestCase
{
  public function test_settings_surface_is_added_to_the_plugins_sidebar_group_when_omitted(): void
  {
    $plugin = $this->pluginDefinition()
      ->menu([
        PluginMenuItem::make('appointments')->label('Appointments')->route('appointments.index')->group('Appointments')->sort(60),
      ])
      ->settings(PluginSettingsDefinition::make('appointments.settings')->label('Settings'));

    $items = $this->registryWith($plugin)->menuItems();

    $this->assertCount(2, $items);
    $this->assertSame('appointments.settings', $items[1]['item']->routeName());
    $this->assertSame('Appointments', $items[1]['item']->groupName());
    $this->assertSame(61, $items[1]['item']->sortOrder());
    $this->assertSame('wb-icon-settings', $items[1]['item']->iconClass());
  }

  public function test_explicit_settings_menu_item_is_not_duplicated(): void
  {
    $plugin = $this->pluginDefinition()
      ->menu([
        PluginMenuItem::make('settings')->label('Settings')->route('appointments.settings')->group('Appointments'),
      ])
      ->settings(PluginSettingsDefinition::make('appointments.settings'));

    $this->assertCount(1, $this->registryWith($plugin)->menuItems());
  }

  public function test_admin_layout_uses_each_plugin_menu_route_family_for_active_state(): void
  {
    $layout = (string) file_get_contents(__DIR__.'/../../resources/views/layouts/admin.blade.php');

    $this->assertStringContainsString("Str::beforeLast(\$pluginRouteName, '.').'.*'", $layout);
    $this->assertStringNotContainsString("routeNamePrefix().'.*'", $layout);
    $this->assertStringContainsString("\$activeGroup['item']['label'] ?? \$activeTopItem['label']", $layout);
  }

  public function test_public_route_prefix_and_name_prefix_are_derived_from_the_handle(): void
  {
    $plugin = $this->pluginDefinition();

    $this->assertSame('/plugins/appointments', $plugin->publicRoutePrefix());
    $this->assertSame('webblocks.plugins.appointments.public', $plugin->publicRouteNamePrefix());
  }

  public function test_public_route_name_prefix_uses_the_snake_case_route_segment(): void
  {
    $plugin = PluginDefinition::make('online-booking')->label('Online Booking')->version('1.0.0');

    $this->assertSame('/plugins/online-booking', $plugin->publicRoutePrefix());
    $this->assertSame('webblocks.plugins.online_booking.public', $plugin->publicRouteNamePrefix());
  }

  public function test_an_empty_public_route_file_is_rejected(): void
  {
    $this->expectException(PluginException::class);

    $this->pluginDefinition()->publicRoutes('   ');
  }

  public function test_a_missing_public_route_file_is_rejected_at_registration(): void
  {
    $plugin = $this->pluginDefinition()->publicRoutes(__DIR__.'/does-not-exist.php');

    $this->expectException(PluginException::class);

    (new PluginPublicRouteRegistrar($this->registryWith($plugin)))->registerEnabledPublicRoutes();
  }

  public function test_describe_reports_the_declared_public_route_count(): void
  {
    $plugin = $this->pluginDefinition()->publicRoutes(fn () => null);

    $described = $plugin->toArray(true);

    $this->assertSame(1, $described['public_routes_count']);
    $this->assertSame('/plugins/appointments', $described['public_route_prefix']);
  }

  public function test_enabled_plugin_public_routes_are_mounted_under_the_reserved_prefix(): void
  {
    $plugin = $this->pluginDefinition()->publicRoutes(function (): void {
      Route::post('/bookings', fn () => 'ok')->name('bookings.store');
    });

    (new PluginPublicRouteRegistrar($this->registryWith($plugin)))->registerEnabledPublicRoutes();

    $route = Route::getRoutes()->getByName('webblocks.plugins.appointments.public.bookings.store');

    $this->assertNotNull($route);
    $this->assertSame('plugins/appointments/bookings', $route->uri());
  }

  public function test_the_registrar_forces_the_public_middleware_stack_and_a_throttle(): void
  {
    $plugin = $this->pluginDefinition()->publicRoutes(function (): void {
      Route::post('/bookings', fn () => 'ok')->name('bookings.store');
    });

    (new PluginPublicRouteRegistrar($this->registryWith($plugin)))->registerEnabledPublicRoutes();

    $middleware = Route::getRoutes()->getByName('webblocks.plugins.appointments.public.bookings.store')->gatherMiddleware();

    $this->assertContains('web', $middleware);
    $this->assertContains('install.required', $middleware);
    $this->assertContains('throttle:plugin-public-routes', $middleware);
  }

  public function test_a_plugin_can_still_add_a_stricter_throttle_of_its_own(): void
  {
    $plugin = $this->pluginDefinition()->publicRoutes(function (): void {
      Route::post('/bookings', fn () => 'ok')
        ->middleware('throttle:3,1')
        ->name('bookings.store');
    });

    (new PluginPublicRouteRegistrar($this->registryWith($plugin)))->registerEnabledPublicRoutes();

    $middleware = Route::getRoutes()->getByName('webblocks.plugins.appointments.public.bookings.store')->gatherMiddleware();

    $this->assertContains('throttle:plugin-public-routes', $middleware);
    $this->assertContains('throttle:3,1', $middleware);
  }

  public function test_a_disabled_plugin_contributes_no_public_routes(): void
  {
    $plugin = $this->pluginDefinition()->publicRoutes(function (): void {
      Route::post('/bookings', fn () => 'ok')->name('bookings.store');
    });

    $registry = new PluginRegistry(['appointments' => false]);
    $registry->register($plugin);

    (new PluginPublicRouteRegistrar($registry))->registerEnabledPublicRoutes();

    $this->assertNull(Route::getRoutes()->getByName('webblocks.plugins.appointments.public.bookings.store'));
  }

  public function test_the_plugin_public_route_rate_limiter_is_registered(): void
  {
    $limiter = app(RateLimiter::class)->limiter('plugin-public-routes');

    $this->assertNotNull($limiter);
  }

  public function test_webhook_routes_mount_beside_the_public_ones(): void
  {
    $plugin = $this->pluginDefinition()->webhookRoutes(function (): void {
      Route::post('/webhooks/provider', fn () => 'ok')->name('webhooks.provider');
    });

    (new PluginPublicRouteRegistrar($this->registryWith($plugin)))->registerEnabledPublicRoutes();

    $route = Route::getRoutes()->getByName('webblocks.plugins.appointments.public.webhooks.provider');

    $this->assertNotNull($route);
    $this->assertSame('plugins/appointments/webhooks/provider', $route->uri());
  }

  /**
   * The reason the group exists. A gateway calling back after a customer pays
   * carries no session and so cannot carry a CSRF token; with the check in place
   * every callback is a 419 that nobody sees, because the caller is a machine.
   */
  public function test_a_webhook_route_is_exempt_from_csrf(): void
  {
    $plugin = $this->pluginDefinition()->webhookRoutes(function (): void {
      Route::post('/webhooks/provider', fn () => 'ok')->name('webhooks.provider');
    });

    (new PluginPublicRouteRegistrar($this->registryWith($plugin)))->registerEnabledPublicRoutes();

    $excluded = Route::getRoutes()
      ->getByName('webblocks.plugins.appointments.public.webhooks.provider')
      ->excludedMiddleware();

    $this->assertContains('Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken', $excluded);
    $this->assertContains('App\\Http\\Middleware\\VerifyCsrfToken', $excluded);
  }

  /**
   * Only that. A webhook is still public, still throttled, and still has to
   * verify for itself that the caller is who it claims to be.
   */
  public function test_a_webhook_route_keeps_the_rest_of_the_public_stack(): void
  {
    $plugin = $this->pluginDefinition()->webhookRoutes(function (): void {
      Route::post('/webhooks/provider', fn () => 'ok')->name('webhooks.provider');
    });

    (new PluginPublicRouteRegistrar($this->registryWith($plugin)))->registerEnabledPublicRoutes();

    $middleware = Route::getRoutes()
      ->getByName('webblocks.plugins.appointments.public.webhooks.provider')
      ->gatherMiddleware();

    $this->assertContains('web', $middleware);
    $this->assertContains('install.required', $middleware);
    $this->assertContains('throttle:plugin-public-routes', $middleware);
  }

  public function test_the_csrf_exemption_does_not_reach_ordinary_public_routes(): void
  {
    $plugin = $this->pluginDefinition()
      ->publicRoutes(function (): void {
        Route::post('/bookings', fn () => 'ok')->name('bookings.store');
      })
      ->webhookRoutes(function (): void {
        Route::post('/webhooks/provider', fn () => 'ok')->name('webhooks.provider');
      });

    (new PluginPublicRouteRegistrar($this->registryWith($plugin)))->registerEnabledPublicRoutes();

    $excluded = Route::getRoutes()
      ->getByName('webblocks.plugins.appointments.public.bookings.store')
      ->excludedMiddleware();

    $this->assertNotContains('Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken', $excluded);
  }

  public function test_a_disabled_plugin_contributes_no_webhook_routes(): void
  {
    $plugin = $this->pluginDefinition()->webhookRoutes(function (): void {
      Route::post('/webhooks/provider', fn () => 'ok')->name('webhooks.provider');
    });

    $registry = new PluginRegistry(['appointments' => false]);
    $registry->register($plugin);

    (new PluginPublicRouteRegistrar($registry))->registerEnabledPublicRoutes();

    $this->assertNull(Route::getRoutes()->getByName('webblocks.plugins.appointments.public.webhooks.provider'));
  }

  public function test_an_empty_webhook_route_file_is_rejected(): void
  {
    $this->expectException(PluginException::class);

    $this->pluginDefinition()->webhookRoutes('   ');
  }

  public function test_describe_reports_the_declared_webhook_route_count(): void
  {
    $described = $this->pluginDefinition()->webhookRoutes(fn () => null)->toArray(true);

    $this->assertSame(1, $described['webhook_routes_count']);
  }

  public function test_a_declared_public_view_wins_over_the_directory_convention(): void
  {
    $this->registerBlockTypePlugin(
      publicView: 'webblocks-cms::pages.partials.blocks.alert',
    );

    $this->assertSame(
      'webblocks-cms::pages.partials.blocks.alert',
      $this->pluginBlock()->publicRenderView(),
    );
  }

  public function test_a_declared_admin_view_wins_over_the_directory_convention(): void
  {
    $this->registerBlockTypePlugin(
      adminView: 'webblocks-cms::admin.blocks.types.alert',
    );

    $this->assertSame(
      'webblocks-cms::admin.blocks.types.alert',
      $this->pluginBlock()->adminFormView(),
    );
  }

  public function test_a_declared_view_that_does_not_exist_falls_back_instead_of_throwing(): void
  {
    $this->registerBlockTypePlugin(
      publicView: 'webblocks-cms::pages.partials.blocks.nothing-ships-this',
    );

    $this->assertNotSame(
      'webblocks-cms::pages.partials.blocks.nothing-ships-this',
      $this->pluginBlock()->publicRenderView(),
    );
  }

  public function test_a_disabled_plugin_block_type_does_not_contribute_a_view(): void
  {
    $this->registerBlockTypePlugin(
      publicView: 'webblocks-cms::pages.partials.blocks.alert',
      enabled: false,
    );

    $this->assertNotSame(
      'webblocks-cms::pages.partials.blocks.alert',
      $this->pluginBlock()->publicRenderView(),
    );
  }

  public function test_core_block_view_resolution_is_untouched_by_the_plugin_lookup(): void
  {
    $this->registerBlockTypePlugin(publicView: 'webblocks-cms::pages.partials.blocks.alert');

    $block = new Block;
    $block->type = 'hero';

    $this->assertSame('webblocks-cms::pages.partials.blocks.hero', $block->publicRenderView());
  }

  private function pluginDefinition(): PluginDefinition
  {
    return PluginDefinition::make('appointments')->label('Appointments')->version('1.0.0');
  }

  private function registryWith(PluginDefinition $plugin): PluginRegistry
  {
    $registry = new PluginRegistry([$plugin->handle() => true]);
    $registry->register($plugin);

    return $registry;
  }

  /**
   * Register an `appointments` plugin owning the `appointments::form` block
   * type, whose catalog slug is `appointments-form`, and bind the registry so
   * `PluginBlockCatalog` resolves against it.
   */
  private function registerBlockTypePlugin(?string $publicView = null, ?string $adminView = null, bool $enabled = true): void
  {
    $blockType = PluginBlockTypeDefinition::make('appointments::form')->label('Appointment Form');

    if ($publicView !== null) {
      $blockType->publicView($publicView);
    }

    if ($adminView !== null) {
      $blockType->adminView($adminView);
    }

    $plugin = $this->pluginDefinition()->blockTypes([$blockType]);

    $registry = new PluginRegistry(['appointments' => $enabled]);
    $registry->register($plugin);

    $this->app->instance(PluginRegistry::class, $registry);
    $this->app->forgetInstance(PluginBlockCatalog::class);
    $this->app->instance(PluginBlockCatalog::class, new PluginBlockCatalog($registry));
  }

  private function pluginBlock(): Block
  {
    $block = new Block;
    $block->type = 'appointments-form';

    return $block;
  }
}
