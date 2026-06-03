<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Http\Middleware\GuardPluginSetup;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Models\WebBlocksUiRelease;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Plugins\PluginAccessResolver;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginHealthMonitor;
use WebBlocks\Cms\Support\Plugins\PluginMenuItem;
use WebBlocks\Cms\Support\Plugins\PluginPermission;
use WebBlocks\Cms\Support\Plugins\PluginPermissionRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRouteRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginZipInstaller;
use ZipArchive;

class AdminPluginListingTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    config()->set('webblocks-plugins.install.root', storage_path('framework/testing/plugins/'.str()->uuid()));
    config()->set('webblocks-plugins.catalog.base_url', 'https://plugins.example.test');
    Http::fake([
      'https://plugins.example.test/api/plugins?*' => Http::response(['data' => []]),
    ]);
    $this->app->forgetInstance(PluginRegistry::class);
    $this->app->forgetInstance(PluginHealthMonitor::class);
  }

  #[Test]
  public function super_admin_can_view_empty_plugin_host_listing_and_upload_action(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.plugins.index'));

    $response->assertOk();
    $response->assertSeeText('Plugins');
    $response->assertSeeText('Manual Plugin Install');
    $response->assertSee('class="wb-btn wb-btn-primary"', false);
    $response->assertSeeText('Upload Plugin ZIP');
    $response->assertSeeText('No plugins registered yet.');
    $response->assertDontSeeText('WebBlocks UI Manager');
  }

  #[Test]
  public function non_system_users_cannot_view_plugins_listing(): void
  {
    $user = User::factory()->editor()->create();

    $this->actingAs($user)
      ->get(route('admin.system.plugins.index'))
      ->assertForbidden();
  }

  #[Test]
  public function uploaded_plugin_appears_disabled_with_manual_source(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
      ->post(route('admin.system.plugins.upload'), [
        'plugin_zip' => $this->webBlocksUiManagerZip(),
      ])
      ->assertRedirect(route('admin.system.plugins.index'));

    $response = $this->actingAs($user)->get(route('admin.system.plugins.index'));

    $response->assertOk();
    $response->assertSeeText('WebBlocks UI Manager');
    $response->assertSeeText('webblocks-ui-manager');
    $response->assertSeeText('0.1.1');
    $response->assertSeeText('Disabled');
    $response->assertSeeText('Inactive');
    $response->assertSeeText('Not checked while disabled.');
    $response->assertSeeText('manual upload');
    $response->assertSeeText('Actions');
    $response->assertDontSeeText('Provider');
    $response->assertDontSeeText('Permissions');
    $response->assertDontSeeText('Menu Items');
    $response->assertDontSeeText('Settings Namespace');
    $response->assertDontSeeText('Route Namespace');
    $response->assertDontSeeText('Database Prefix');
    $response->assertDontSeeText('Install Path');
  }

  #[Test]
  public function plugin_detail_is_available_from_name_link_and_view_action(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $response = $this->actingAs($user)->get(route('admin.system.plugins.index'));
    $detailUrl = route('admin.system.plugins.show', 'webblocks-ui-manager');

    $response->assertOk();
    $this->assertGreaterThanOrEqual(2, substr_count($response->getContent(), $detailUrl));

    $this->actingAs($user)
      ->get($detailUrl)
      ->assertOk()
      ->assertSeeText('Overview')
      ->assertSeeText('Lifecycle')
      ->assertSeeText('This plugin is installed but disabled. Enable it to register its routes, commands, menus, settings, health checks, and contributions.')
      ->assertSeeText('Available after enabling');
  }

  #[Test]
  public function uploaded_plugin_can_be_explicitly_enabled_from_the_list(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $response = $this->actingAs($user)->get(route('admin.system.plugins.index'));

    $response->assertOk();
    $response->assertSeeText('WebBlocks UI Manager');
    $response->assertSeeText('Enabled');
  }

  #[Test]
  public function enabled_plugin_sidebar_link_opens_plugin_route_without_dashboard_redirect(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $this->dropWebBlocksUiManagerTables();
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();

    $dashboard = $this->actingAs($user)->get(route('admin.dashboard'));

    $dashboard->assertOk();
    $dashboard->assertSeeText('WebBlocks UI Releases');
    $dashboard->assertSee('href="/webadmin/plugins/webblocks-ui-manager/releases"', false);

    $response = $this->actingAs($user)->get('/webadmin/plugins/webblocks-ui-manager/releases');

    $response->assertOk();
    $response->assertSeeText('Setup Required');
    $response->assertSeeText('Plugin Migrations Pending');
    $response->assertSeeText('Release tables are missing');
  }

  #[Test]
  public function enabled_plugin_releases_route_uses_same_url_setup_state_when_dynamic_routes_are_not_hydrated(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $this->dropWebBlocksUiManagerTables();

    $response = $this->actingAs($user)->get('/webadmin/plugins/webblocks-ui-manager/releases');

    $response->assertOk();
    $response->assertHeaderMissing('Location');
    $response->assertSeeText('WebBlocks UI Releases');
    $response->assertSeeText('Plugin Migrations Pending');
    $response->assertSeeText('Release tables are missing');
    $response->assertSeeText('Run Plugin Migrations');
    $response->assertSee(route('admin.system.plugins.show', 'webblocks-ui-manager'), false);
  }

  #[Test]
  public function enabled_plugin_releases_route_renders_after_setup_without_dashboard_redirect(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $this->dropWebBlocksUiManagerTables();
    $this->assertFalse(Schema::hasTable('webblocks_ui_manager_releases'));

    $this->actingAs($user)
      ->post(route('admin.system.plugins.setup', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $response = $this->actingAs($user)->get('/webadmin/plugins/webblocks-ui-manager/releases');

    $response->assertOk();
    $response->assertHeaderMissing('Location');
    $response->assertSeeText('WebBlocks UI Releases');
    $response->assertSeeText('No WebBlocks UI releases recorded yet.');
  }

  #[Test]
  public function enabled_plugin_settings_route_uses_same_url_fallback_for_super_admin(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $this->dropWebBlocksUiManagerTables();

    $response = $this->actingAs($user)->get('/webadmin/plugins/webblocks-ui-manager/settings');

    $response->assertOk();
    $response->assertHeaderMissing('Location');
    $response->assertSeeText('WebBlocks UI Manager Settings');
  }

  #[Test]
  public function enabled_plugin_entry_routes_are_core_bridged_without_dashboard_fallback(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();

    $releaseRoute = Route::getRoutes()->getByName('webblocks.plugins.webblocks_ui_manager.releases.index');
    $settingsRoute = Route::getRoutes()->getByName('webblocks.plugins.webblocks_ui_manager.settings.edit');

    $this->assertSame('webadmin/plugins/webblocks-ui-manager/releases', $releaseRoute?->uri());
    $this->assertSame('webadmin/plugins/webblocks-ui-manager/settings', $settingsRoute?->uri());
    $this->assertSame(
      'WebBlocks\\Cms\\Http\\Controllers\\Admin\\PluginRouteFallbackController',
      $releaseRoute?->getActionName()
    );
    $this->assertSame(
      'WebBlocks\\Cms\\Http\\Controllers\\Admin\\PluginRouteFallbackController',
      $settingsRoute?->getActionName()
    );
  }

  #[Test]
  public function plugin_detail_open_settings_link_uses_plugin_admin_url_and_does_not_dashboard_redirect(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $this->dropWebBlocksUiManagerTables();

    $detail = $this->actingAs($user)->get(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $detail->assertOk();
    $detail->assertSeeText('Open Settings');
    $detail->assertSee('/webadmin/plugins/webblocks-ui-manager/settings', false);

    $this->actingAs($user)
      ->get('/webadmin/plugins/webblocks-ui-manager/settings')
      ->assertOk()
      ->assertHeaderMissing('Location')
      ->assertSeeText('WebBlocks UI Manager Settings');
  }

  #[Test]
  public function enabled_plugin_new_release_route_is_bridged_without_dashboard_fallback(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $this->dropWebBlocksUiManagerTables();

    $this->actingAs($user)
      ->get('/webadmin/plugins/webblocks-ui-manager/releases/create')
      ->assertOk()
      ->assertHeaderMissing('Location')
      ->assertSeeText('Plugin Migrations Pending')
      ->assertSeeText('Release tables are missing');

    $this->actingAs($user)
      ->post(route('admin.system.plugins.setup', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $index = $this->actingAs($user)->get('/webadmin/plugins/webblocks-ui-manager/releases');

    $index->assertOk();
    $index->assertSee('href="'.route('webblocks.plugins.webblocks_ui_manager.releases.create').'"', false);

    $this->actingAs($user)
      ->get('/webadmin/plugins/webblocks-ui-manager/releases/create')
      ->assertOk()
      ->assertHeaderMissing('Location')
      ->assertSeeText('New WebBlocks UI Release')
      ->assertSeeText('Release Metadata');
  }

  #[Test]
  public function enabled_plugin_release_detail_edit_and_publish_routes_are_bridged_without_dashboard_fallback(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $this->dropWebBlocksUiManagerTables();

    $this->assertFalse(Schema::hasTable('webblocks_ui_manager_releases'));

    $this->actingAs($user)
      ->get('/webadmin/plugins/webblocks-ui-manager/releases/1')
      ->assertOk()
      ->assertHeaderMissing('Location')
      ->assertSeeText('Plugin Migrations Pending');

    $this->actingAs($user)
      ->get('/webadmin/plugins/webblocks-ui-manager/releases/1/edit')
      ->assertOk()
      ->assertHeaderMissing('Location')
      ->assertSeeText('Plugin Migrations Pending');

    $this->actingAs($user)
      ->post(route('admin.system.plugins.setup', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $create = $this->actingAs($user)
      ->post('/webadmin/plugins/webblocks-ui-manager/releases', [
        'version' => 'v2.8.0',
        'label' => 'WebBlocks UI v2.8.0',
        'status' => 'draft',
        'cdn_base_path' => 'public/cdn/webblocks-ui/v2.8.0',
        'cdn_base_url' => 'https://cdn.example.test/webblocks-ui/v2.8.0',
        'notes' => 'Bridge regression fixture.',
      ]);

    $create->assertRedirect();
    $this->assertNotSame(route('admin.dashboard'), $create->headers->get('Location'));

    $release = WebBlocksUiRelease::query()
      ->where('version', 'v2.8.0')
      ->firstOrFail();

    $this->actingAs($user)
      ->get('/webadmin/plugins/webblocks-ui-manager/releases/'.$release->id)
      ->assertOk()
      ->assertHeaderMissing('Location')
      ->assertSeeText('WebBlocks UI v2.8.0');

    $this->actingAs($user)
      ->get('/webadmin/plugins/webblocks-ui-manager/releases/'.$release->id.'/edit')
      ->assertOk()
      ->assertHeaderMissing('Location')
      ->assertSeeText('Edit WebBlocks UI Release');

    $update = $this->actingAs($user)
      ->put('/webadmin/plugins/webblocks-ui-manager/releases/'.$release->id, [
        'version' => 'v2.8.0',
        'label' => 'WebBlocks UI v2.8.0 Updated',
        'status' => 'draft',
        'cdn_base_path' => 'public/cdn/webblocks-ui/v2.8.0',
        'cdn_base_url' => 'https://cdn.example.test/webblocks-ui/v2.8.0',
        'notes' => 'Updated bridge regression fixture.',
      ]);

    $update->assertRedirect();
    $this->assertNotSame(route('admin.dashboard'), $update->headers->get('Location'));

    $dryRun = $this->actingAs($user)
      ->post('/webadmin/plugins/webblocks-ui-manager/releases/'.$release->id.'/publish-dry-run');

    $dryRun->assertRedirect();
    $this->assertNotSame(route('admin.dashboard'), $dryRun->headers->get('Location'));

    $publish = $this->actingAs($user)
      ->post('/webadmin/plugins/webblocks-ui-manager/releases/'.$release->id.'/publish');

    $publish->assertRedirect();
    $this->assertNotSame(route('admin.dashboard'), $publish->headers->get('Location'));
  }

  #[Test]
  public function enabled_plugin_with_missing_tables_shows_setup_required_release_screen_instead_of_500(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $this->dropWebBlocksUiManagerTables();
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();

    $response = $this->actingAs($user)->get('/webadmin/plugins/webblocks-ui-manager/releases');

    $response->assertOk();
    $response->assertSeeText('Setup Required');
    $response->assertSeeText('Plugin Migrations Pending');
    $response->assertSeeText('Release tables are missing');
    $response->assertSee(route('admin.system.plugins.show', 'webblocks-ui-manager'), false);
  }

  #[Test]
  public function enabled_manual_plugin_permissions_are_registered_for_authorization(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $this->app->forgetInstance(PluginRegistry::class);
    $this->app->forgetInstance(PluginPermissionRegistry::class);

    $permissions = app(PluginPermissionRegistry::class)->active();

    $this->assertArrayHasKey('webblocks-ui-manager', $permissions);
    $this->assertArrayHasKey('webblocks-ui-manager.view', $permissions['webblocks-ui-manager']);
    $this->assertArrayHasKey('webblocks-ui-manager.manage', $permissions['webblocks-ui-manager']);
    $this->assertArrayHasKey('webblocks-ui-manager.publish', $permissions['webblocks-ui-manager']);
    $this->assertTrue($user->can('webblocks-ui-manager.view'));
    $this->assertTrue($user->can('webblocks-ui-manager.manage'));
    $this->assertTrue($user->can('webblocks-ui-manager.publish'));
  }

  #[Test]
  public function super_admin_can_access_enabled_plugin_releases_and_settings_before_setup(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $this->dropWebBlocksUiManagerTables();
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();

    $this->actingAs($user)
      ->get('/webadmin/plugins/webblocks-ui-manager/releases')
      ->assertOk()
      ->assertSeeText('Setup Required')
      ->assertSeeText('Plugin Migrations Pending');

    $this->actingAs($user)
      ->get('/webadmin/plugins/webblocks-ui-manager/settings')
      ->assertOk()
      ->assertSeeText('WebBlocks UI Manager Settings');
  }

  #[Test]
  public function super_admin_can_access_enabled_plugin_releases_and_settings_after_setup(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $this->dropWebBlocksUiManagerTables();

    $this->actingAs($user)
      ->post(route('admin.system.plugins.setup', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();

    $this->actingAs($user)
      ->get('/webadmin/plugins/webblocks-ui-manager/releases')
      ->assertOk()
      ->assertSeeText('Tracked Releases');

    $this->actingAs($user)
      ->get('/webadmin/plugins/webblocks-ui-manager/settings')
      ->assertOk()
      ->assertSeeText('WebBlocks UI Manager Settings');
  }

  #[Test]
  public function plugin_routes_still_deny_non_super_admins_without_plugin_permission_grants(): void
  {
    $superAdmin = User::factory()->superAdmin()->create();
    $siteAdmin = User::factory()->siteAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($superAdmin);

    $this->actingAs($superAdmin)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();

    $this->actingAs($siteAdmin)
      ->get('/webadmin/plugins/webblocks-ui-manager/releases')
      ->assertForbidden();

    $this->actingAs($siteAdmin)
      ->get('/webadmin/plugins/webblocks-ui-manager/settings')
      ->assertForbidden();
  }

  #[Test]
  public function guests_are_redirected_from_enabled_plugin_routes(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    auth()->logout();

    $this->get('/webadmin/plugins/webblocks-ui-manager/releases')
      ->assertRedirect(route('login'));

    $this->get('/webadmin/plugins/webblocks-ui-manager/settings')
      ->assertRedirect(route('login'));
  }

  #[Test]
  public function super_admin_can_run_manual_plugin_migrations_idempotently(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $this->dropWebBlocksUiManagerTables();
    $this->assertFalse(Schema::hasTable('webblocks_ui_manager_releases'));

    $this->actingAs($user)
      ->post(route('admin.system.plugins.setup', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'))
      ->assertSessionHas('status', 'Plugin migrations completed.');

    $this->assertTrue(Schema::hasTable('webblocks_ui_manager_releases'));
    $this->assertTrue(Schema::hasTable('webblocks_ui_manager_artifacts'));
    $this->assertTrue(Schema::hasTable('webblocks_ui_manager_publish_runs'));
    $this->assertFalse(Schema::hasTable('webblocks_ui_manager_unrelated_table'));

    $this->actingAs($user)
      ->post(route('admin.system.plugins.setup', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'))
      ->assertSessionHas('status', 'Plugin migrations completed.');

    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();

    $this->actingAs($user)
      ->get('/webadmin/plugins/webblocks-ui-manager/releases')
      ->assertOk()
      ->assertSeeText('Tracked Releases');
  }

  #[Test]
  public function plugin_setup_action_is_super_admin_only(): void
  {
    $superAdmin = User::factory()->superAdmin()->create();
    $editor = User::factory()->editor()->create();

    $this->uploadWebBlocksUiManagerPlugin($superAdmin);

    $this->actingAs($superAdmin)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $this->dropWebBlocksUiManagerTables();
    $this->actingAs($editor)
      ->post(route('admin.system.plugins.setup', 'webblocks-ui-manager'))
      ->assertForbidden();

    $this->assertFalse(Schema::hasTable('webblocks_ui_manager_releases'));
  }

  #[Test]
  public function uploaded_plugin_can_be_enabled_and_disabled_from_detail(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $this->actingAs($user)
      ->get(route('admin.system.plugins.show', 'webblocks-ui-manager'))
      ->assertOk()
      ->assertSeeText('Enable Plugin');

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $this->actingAs($user)
      ->get(route('admin.system.plugins.show', 'webblocks-ui-manager'))
      ->assertOk()
      ->assertSeeText('Disable Plugin');

    $this->actingAs($user)
      ->post(route('admin.system.plugins.disable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $this->actingAs($user)
      ->get(route('admin.system.plugins.show', 'webblocks-ui-manager'))
      ->assertOk()
      ->assertSeeText('Disabled')
      ->assertSeeText('Inactive');
  }

  #[Test]
  public function plugin_management_ui_uses_standard_action_layouts(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $list = $this->actingAs($user)->get(route('admin.system.plugins.index'));

    $list->assertOk();
    $list->assertDontSeeText('WebBlocks UI CDN Foundation');
    $list->assertDontSee('data-plugin-system-card=', false);
    $list->assertSee('class="wb-btn wb-btn-primary"', false);
    $list->assertSeeText('Upload Plugin ZIP');
    $list->assertSee('td class="wb-table-actions"', false);
    $list->assertSee('class="wb-action-group"', false);
    $list->assertDontSee('td class="wb-table-actions wb-whitespace-nowrap"', false);
    $list->assertDontSee('class="wb-action-group wb-whitespace-nowrap"', false);

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();

    $detail = $this->actingAs($user)->get(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $detail->assertOk();
    $detail->assertSeeText('Run Plugin Migrations');
    $detail->assertSee('class="wb-card-footer"', false);
    $detail->assertSee('Open Settings');
    $detail->assertDontSee('wb-btn-danger wb-w-full', false);
  }

  #[Test]
  public function registered_plugins_show_catalog_update_availability_for_newer_compatible_releases(): void
  {
    $this->installSampleToolsPlugin('1.0.0');
    $this->fakeCatalogHttp($this->catalogUpdateFakeResponses('1.2.0'));

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.plugins.index'));

    $response->assertOk();
    $response->assertSeeText('Sample Tools');
    $response->assertSeeText('1.0.0');
    $response->assertSeeText('Update available: 1.2.0');
    $response->assertSee('action="'.route('admin.system.plugins.update-from-catalog', 'sample-tools').'"', false);
    $response->assertSee('title="Update from Catalog"', false);
    $response->assertSee('class="wb-action-group"', false);
  }

  #[Test]
  public function registered_plugins_hide_catalog_update_action_when_current_incompatible_or_incomplete(): void
  {
    $user = User::factory()->superAdmin()->create();

    foreach ([
      'current' => $this->catalogUpdateFakeResponses('1.0.0'),
      'incompatible' => $this->catalogUpdateFakeResponses('1.2.0', compatible: false),
      'incomplete' => $this->catalogUpdateFakeResponses('1.2.0', completeArtifact: false),
    ] as $responses) {
      $this->installSampleToolsPlugin('1.0.0');
      $this->fakeCatalogHttp($responses);
      $this->app->forgetInstance(PluginRegistry::class);

      $response = $this->actingAs($user)->get(route('admin.system.plugins.index'));

      $response->assertOk();
      $response->assertDontSeeText('Update available: 1.2.0');
      $response->assertDontSee('action="'.route('admin.system.plugins.update-from-catalog', 'sample-tools').'"', false);

      $this->app->make(InstalledPluginRepository::class)->uninstall('sample-tools', '1.0.0');
      $this->app->forgetInstance(PluginRegistry::class);
    }
  }

  #[Test]
  public function catalog_unavailable_does_not_break_registered_plugins_list(): void
  {
    $this->installSampleToolsPlugin('1.0.0');
    $this->fakeCatalogHttp(fn () => throw new ConnectionException('timeout'));

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.plugins.index'));

    $response->assertOk();
    $response->assertSeeText('Sample Tools');
    $response->assertDontSeeText('Update available');
    $response->assertDontSee('Update from Catalog', false);
  }

  #[Test]
  public function post_update_from_catalog_verifies_and_replaces_plugin_package_preserving_lifecycle_and_tables(): void
  {
    $this->installSampleToolsPlugin('1.0.0', [
      'migrations' => ['database/migrations'],
    ]);

    app(InstalledPluginRepository::class)->enable('sample-tools', '1.0.0');
    Schema::create('sample_tools_records', function ($table): void {
      $table->id();
    });

    $zip = $this->sampleToolsZipBody('1.2.0', [
      'migrations' => ['database/migrations'],
    ], [
      'database/migrations/2026_01_01_000000_create_sample_tools_auto_migrations_table.php' => <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('sample_tools_auto_migrations', function (Blueprint $table): void {
      $table->id();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('sample_tools_auto_migrations');
  }
};
PHP,
    ]);

    $this->fakeCatalogHttp($this->catalogUpdateFakeResponses('1.2.0', zip: $zip));

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->post(route('admin.system.plugins.update-from-catalog', 'sample-tools'));

    $response->assertRedirect(route('admin.system.plugins.index'));
    $response->assertSessionHas('status', 'Sample Tools updated to 1.2.0 from Plugin Catalog.');
    $this->assertFileDoesNotExist(config('webblocks-plugins.install.root').'/sample-tools/1.0.0/webblocks-plugin.json');
    $this->assertFileExists(config('webblocks-plugins.install.root').'/sample-tools/1.2.0/webblocks-plugin.json');
    $this->assertSame('1.2.0', app(InstalledPluginRepository::class)->enabledVersion('sample-tools'));
    $this->assertTrue(Schema::hasTable('sample_tools_records'));
    $this->assertFalse(Schema::hasTable('sample_tools_auto_migrations'));

    $this->app->forgetInstance(PluginRegistry::class);

    $this->actingAs($user)
      ->get(route('admin.system.plugins.index'))
      ->assertOk()
      ->assertSeeText('1.2.0');
  }

  #[Test]
  public function catalog_update_refreshes_active_manifest_runtime_path_and_controller_source(): void
  {
    app(PluginZipInstaller::class)->install($this->redirectManagerZipPath('0.1.8', includeController: false));
    app(InstalledPluginRepository::class)->enable('webblocks-redirect-manager', '0.1.8');
    $this->app->forgetInstance(PluginRegistry::class);

    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    $oldDefinition = app(PluginRegistry::class)->get('webblocks-redirect-manager');

    $this->assertSame('0.1.8', $oldDefinition?->versionText());
    $this->assertStringEndsWith('/webblocks-redirect-manager/0.1.8', (string) $oldDefinition?->installPathValue());

    $zip = (string) file_get_contents($this->redirectManagerZipPath('0.1.9'));
    $this->fakeCatalogHttp($this->catalogUpdateFakeResponses(
      '0.1.9',
      zip: $zip,
      handle: 'webblocks-redirect-manager',
      label: 'WebBlocks Redirect Manager',
    ));

    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
      ->post(route('admin.system.plugins.update-from-catalog', 'webblocks-redirect-manager'))
      ->assertRedirect(route('admin.system.plugins.index'))
      ->assertSessionHas('status', 'WebBlocks Redirect Manager updated to 0.1.9 from Plugin Catalog.');

    $registry = app(PluginRegistry::class);
    $definition = $registry->get('webblocks-redirect-manager');
    $route = Route::getRoutes()->getByName('webblocks.plugins.webblocks_redirect_manager.redirects.index');
    $controller = new \ReflectionClass('Vendor\\RedirectManager\\Http\\Controllers\\RedirectController');

    $this->assertSame('0.1.9', $definition?->versionText());
    $this->assertSame('0.1.9', app(InstalledPluginRepository::class)->enabledVersion('webblocks-redirect-manager'));
    $this->assertStringEndsWith('/webblocks-redirect-manager/0.1.9', (string) $definition?->installPathValue());
    $this->assertSame('webadmin/plugins/webblocks-redirect-manager/redirects', $route?->uri());
    $this->assertSame('Vendor\\RedirectManager\\Http\\Controllers\\RedirectController@index', $route?->getActionName());
    $this->assertSame([
      'web',
      'install.required',
      'auth',
      'admin.access',
      GuardPluginSetup::class.':webblocks-redirect-manager',
      'plugin.permission:webblocks-redirect-manager.view',
    ], $route?->gatherMiddleware());
    $this->assertStringContainsString('/webblocks-redirect-manager/0.1.9/src/Http/Controllers/RedirectController.php', $controller->getFileName());
    $this->assertFileDoesNotExist(config('webblocks-plugins.install.root').'/webblocks-redirect-manager/0.1.8/webblocks-plugin.json');
    $this->assertFalse(Schema::hasTable('webblocks_redirect_manager_redirects'));
  }

  #[Test]
  public function plugin_menu_visibility_and_route_authorization_share_central_resolver(): void
  {
    $registry = new PluginRegistry(['webblocks-redirect-manager' => true]);
    $registry->register(
      PluginDefinition::make('webblocks-redirect-manager')
        ->label('WebBlocks Redirect Manager')
        ->permissions([
          PluginPermission::make('webblocks-redirect-manager.view'),
        ])
        ->menu([
          PluginMenuItem::make('redirects')
            ->label('Redirects')
            ->route('webblocks.plugins.webblocks_redirect_manager.redirects.index')
            ->permission('webblocks-redirect-manager.view'),
        ])
    );

    $superAdmin = User::factory()->superAdmin()->create();
    $siteAdmin = User::factory()->siteAdmin()->create();
    $resolver = app(PluginAccessResolver::class);

    $this->assertTrue($resolver->canAccessPluginPermission($superAdmin, 'webblocks-redirect-manager.view', $registry));
    $this->assertFalse($resolver->canAccessPluginPermission($siteAdmin, 'webblocks-redirect-manager.view', $registry));
    $this->assertCount(1, $registry->menuItems($superAdmin));
    $this->assertSame([], $registry->menuItems($siteAdmin));
  }

  #[Test]
  public function post_update_from_catalog_blocks_checksum_mismatch(): void
  {
    $this->installSampleToolsPlugin('1.0.0');
    $zip = $this->sampleToolsZipBody('1.2.0');

    $this->fakeCatalogHttp($this->catalogUpdateFakeResponses('1.2.0', zip: $zip, checksum: str_repeat('0', 64)));

    $user = User::factory()->superAdmin()->create();

    $this->from(route('admin.system.plugins.index'))
      ->actingAs($user)
      ->post(route('admin.system.plugins.update-from-catalog', 'sample-tools'))
      ->assertRedirect(route('admin.system.plugins.index'))
      ->assertSessionHasErrors(['plugin' => 'The downloaded catalog artifact failed SHA-256 verification.']);

    $this->assertFileExists(config('webblocks-plugins.install.root').'/sample-tools/1.0.0/webblocks-plugin.json');
    $this->assertFileDoesNotExist(config('webblocks-plugins.install.root').'/sample-tools/1.2.0/webblocks-plugin.json');
  }

  #[Test]
  public function disabled_manual_plugin_can_be_uninstalled_without_dropping_plugin_tables(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);
    Artisan::call('migrate', [
      '--path' => 'plugins/webblocks-ui-manager/database/migrations',
      '--realpath' => false,
    ]);

    $installPath = config('webblocks-plugins.install.root').'/webblocks-ui-manager/0.1.1';

    $this->assertDirectoryExists($installPath);
    $this->assertTrue(Schema::hasTable('webblocks_ui_manager_releases'));

    $this->actingAs($user)
      ->delete(route('admin.system.plugins.uninstall', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.index'));

    $this->assertDirectoryDoesNotExist($installPath);
    $this->assertTrue(Schema::hasTable('webblocks_ui_manager_releases'));

    $this->actingAs($user)
      ->get(route('admin.system.plugins.show', 'webblocks-ui-manager'))
      ->assertNotFound();
  }

  #[Test]
  public function enabled_manual_plugin_must_be_disabled_before_uninstall(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $installPath = config('webblocks-plugins.install.root').'/webblocks-ui-manager/0.1.1';

    $this->actingAs($user)
      ->delete(route('admin.system.plugins.uninstall', 'webblocks-ui-manager'))
      ->assertSessionHasErrors('plugin');

    $this->assertDirectoryExists($installPath);
  }

  #[Test]
  public function protected_non_manual_plugins_cannot_be_uninstalled(): void
  {
    $registry = new PluginRegistry(['core-tools' => false]);
    $registry->register(
      PluginDefinition::make('core-tools')
        ->label('Core Tools')
        ->version('1.0.0')
    );
    $this->app->instance(PluginRegistry::class, $registry);

    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
      ->delete(route('admin.system.plugins.uninstall', 'core-tools'))
      ->assertNotFound();
  }

  #[Test]
  public function incompatible_configured_plugin_appears_with_clear_incompatible_status(): void
  {
    $registry = new PluginRegistry(['future-plugin' => true]);
    $registry->register(
      PluginDefinition::make('future-plugin')
        ->label('Future Plugin')
        ->version('9.0.0')
        ->requiresCms('>=99.0.0')
    );
    $this->app->instance(PluginRegistry::class, $registry);
    $this->app->forgetInstance(PluginHealthMonitor::class);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.plugins.index'));

    $response->assertOk();
    $response->assertSeeText('Future Plugin');
    $response->assertSeeText('Incompatible');
    $response->assertSeeText('Requires WebBlocks CMS >=99.0.0');
  }

  #[Test]
  public function plugins_navigation_item_is_visible_only_to_super_admin_users(): void
  {
    $superAdmin = User::factory()->superAdmin()->create();
    $editor = User::factory()->editor()->create();

    $superAdminResponse = $this->actingAs($superAdmin)->get(route('admin.dashboard'));
    $superAdminResponse->assertOk();
    $superAdminResponse->assertSee('href="'.route('admin.system.plugins.index').'"', false);

    $editorResponse = $this->followingRedirects()->actingAs($editor)->get(route('admin.pages.index'));
    $editorResponse->assertOk();
    $editorResponse->assertDontSee('href="'.route('admin.system.plugins.index').'"', false);
  }

  /**
   * @param  array<string, mixed>  $manifestOverride
   */
  private function installSampleToolsPlugin(string $version, array $manifestOverride = []): void
  {
    app(PluginZipInstaller::class)->install($this->sampleToolsZipPath($version, $manifestOverride));
    $this->app->forgetInstance(PluginRegistry::class);
  }

  /**
   * @param  array<string, mixed>  $manifestOverride
   * @param  array<string, string>  $entries
   */
  private function sampleToolsZipBody(string $version, array $manifestOverride = [], array $entries = []): string
  {
    return (string) file_get_contents($this->sampleToolsZipPath($version, $manifestOverride, $entries));
  }

  /**
   * @param  array<string, mixed>  $manifestOverride
   * @param  array<string, string>  $entries
   */
  private function sampleToolsZipPath(string $version, array $manifestOverride = [], array $entries = []): string
  {
    $path = storage_path('framework/testing/sample-tools-'.str()->uuid().'.zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('webblocks-plugin.json', json_encode(array_merge([
      'handle' => 'sample-tools',
      'label' => 'Sample Tools',
      'version' => $version,
      'provider' => 'Vendor\\SampleTools\\SampleToolsPlugin',
      'required_cms_version' => '^1.32',
      'permissions' => [],
      'commands' => [],
      'routes' => [],
      'settings' => [],
      'migrations' => [],
      'assets' => [],
      'health' => null,
    ], $manifestOverride), JSON_PRETTY_PRINT));
    $zip->addFromString('src/SampleToolsPlugin.php', '<?php');

    foreach ($entries as $name => $contents) {
      $zip->addFromString($name, $contents);
    }

    $zip->close();

    return $path;
  }

  private function redirectManagerZipPath(string $version, bool $includeController = true): string
  {
    $path = storage_path('framework/testing/webblocks-redirect-manager-'.$version.'-'.str()->uuid().'.zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('webblocks-plugin.json', json_encode([
      'handle' => 'webblocks-redirect-manager',
      'label' => 'WebBlocks Redirect Manager',
      'version' => $version,
      'provider' => 'Vendor\\RedirectManager\\RedirectManagerPlugin',
      'required_cms_version' => '^1.32',
      'permissions' => [
        ['key' => 'webblocks-redirect-manager.view', 'label' => 'View redirects'],
        ['key' => 'webblocks-redirect-manager.manage', 'label' => 'Manage redirects'],
      ],
      'routes' => ['admin' => 'routes/admin.php'],
      'menu_items' => [
        [
          'key' => 'redirects',
          'label' => 'Redirects',
          'route' => 'webblocks.plugins.webblocks_redirect_manager.redirects.index',
          'permission' => 'webblocks-redirect-manager.view',
          'group' => 'System',
          'sort' => 20,
        ],
      ],
      'migrations' => ['database/migrations'],
    ], JSON_PRETTY_PRINT));
    $zip->addFromString('src/RedirectManagerPlugin.php', <<<'PHP'
<?php

namespace Vendor\RedirectManager;

use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginMenuItem;
use WebBlocks\Cms\Support\Plugins\PluginPermission;

class RedirectManagerPlugin
{
  public static function definition(): PluginDefinition
  {
    return PluginDefinition::make('webblocks-redirect-manager')
      ->label('WebBlocks Redirect Manager')
      ->version('0.1.8')
      ->provider(self::class)
      ->permissions([
        PluginPermission::make('webblocks-redirect-manager.view')->label('View redirects'),
        PluginPermission::make('webblocks-redirect-manager.manage')->label('Manage redirects'),
      ])
      ->menu([
        PluginMenuItem::make('redirects')
          ->label('Redirects')
          ->route('webblocks.plugins.webblocks_redirect_manager.redirects.index')
          ->permission('webblocks-redirect-manager.view'),
      ])
      ->adminRoutes(__DIR__.'/../routes/admin.php');
  }
}
PHP);
    $zip->addFromString('routes/admin.php', <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;
use Vendor\RedirectManager\Http\Controllers\RedirectController;

Route::get('/redirects', [RedirectController::class, 'index'])
  ->middleware('plugin.permission:webblocks-redirect-manager.view')
  ->name('redirects.index');
PHP);

    if ($includeController) {
      $zip->addFromString('src/Http/Controllers/RedirectController.php', <<<'PHP'
<?php

namespace Vendor\RedirectManager\Http\Controllers;

use Illuminate\Support\Facades\DB;

class RedirectController
{
  public function index(): string
  {
    DB::table('webblocks_redirect_manager_redirects')->count();

    return 'Redirect Manager redirects';
  }
}
PHP);
    }

    $zip->close();

    return $path;
  }

  /**
   * @return array<string, mixed>
   */
  private function catalogUpdateFakeResponses(
    string $version,
    bool $compatible = true,
    bool $completeArtifact = true,
    ?string $zip = null,
    ?string $checksum = null,
    string $handle = 'sample-tools',
    string $label = 'Sample Tools',
  ): array {
    $zip ??= $this->sampleToolsZipBody($version);
    $checksum ??= hash('sha256', $zip);
    $artifact = [
      'download_url' => 'https://plugins.example.test/downloads/'.$handle.'-'.$version.'.zip',
      'checksum_sha256' => $checksum,
      'file_name' => $handle.'-'.$version.'.zip',
      'size_bytes' => strlen($zip),
      'validation_status' => 'passed',
    ];

    if (! $completeArtifact) {
      unset($artifact['checksum_sha256']);
    }

    $plugin = [
      'handle' => $handle,
      'label' => $label,
      'compatibility' => ['status' => $compatible ? 'compatible' : 'incompatible'],
    ];
    $release = [
      'version' => $version,
      'channel' => 'stable',
      'status' => 'published',
      'artifact' => $artifact,
    ];

    return [
      'https://plugins.example.test/api/plugins?*' => Http::response([
        'data' => [$plugin],
      ]),
      'https://plugins.example.test/api/plugins/'.$handle.'?*' => Http::response([
        'data' => [
          'plugin' => $plugin,
        ],
      ]),
      'https://plugins.example.test/api/plugins/'.$handle.'/latest?*' => Http::response([
        'data' => [
          'release' => $release,
        ],
      ]),
      'https://plugins.example.test/downloads/'.$handle.'-'.$version.'.zip*' => Http::response($zip, 200, [
        'Content-Length' => (string) strlen($zip),
        'Content-Type' => 'application/zip',
      ]),
    ];
  }

  private function fakeCatalogHttp(array|callable $responses): void
  {
    Http::swap(new Factory);
    Http::fake($responses);
  }

  private function webBlocksUiManagerZip(): UploadedFile
  {
    $zipPath = storage_path('framework/testing/webblocks-ui-manager-'.str()->uuid().'.zip');
    $zip = new ZipArchive;
    $source = base_path('plugins/webblocks-ui-manager');

    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)) as $file) {
      if (! $file->isFile()) {
        continue;
      }

      $relative = str_replace($source.DIRECTORY_SEPARATOR, '', $file->getPathname());
      $normalized = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

      if ($normalized === 'build-plugin.php' || str_starts_with($normalized, '.') || str_contains($normalized, '/.')) {
        continue;
      }

      $zip->addFile($file->getPathname(), str_replace(DIRECTORY_SEPARATOR, '/', $relative));
    }

    $zip->close();

    return new UploadedFile($zipPath, 'webblocks-ui-manager.zip', 'application/zip', null, true);
  }

  private function uploadWebBlocksUiManagerPlugin(User $user): void
  {
    $this->actingAs($user)
      ->post(route('admin.system.plugins.upload'), [
        'plugin_zip' => $this->webBlocksUiManagerZip(),
      ])
      ->assertRedirect(route('admin.system.plugins.index'));
  }

  private function dropWebBlocksUiManagerTables(): void
  {
    Schema::dropIfExists('webblocks_ui_manager_publish_runs');
    Schema::dropIfExists('webblocks_ui_manager_artifacts');
    Schema::dropIfExists('webblocks_ui_manager_releases');
  }
}
