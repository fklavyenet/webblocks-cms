<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginHealthMonitor;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRouteRegistrar;
use ZipArchive;

class AdminPluginListingTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    config()->set('webblocks-plugins.install.root', storage_path('framework/testing/plugins/'.str()->uuid()));
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
    $response->assertSeeText('0.1.0');
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
  public function enabled_plugin_with_missing_tables_shows_setup_required_release_screen_instead_of_500(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->uploadWebBlocksUiManagerPlugin($user);

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $this->dropWebBlocksUiManagerTables();
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    $this->defineWebBlocksUiManagerPermissionGates();

    $response = $this->actingAs($user)->get('/webadmin/plugins/webblocks-ui-manager/releases');

    $response->assertOk();
    $response->assertSeeText('Setup Required');
    $response->assertSeeText('Plugin Migrations Pending');
    $response->assertSeeText('Release tables are missing');
    $response->assertSee(route('admin.system.plugins.show', 'webblocks-ui-manager'), false);
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
    $this->defineWebBlocksUiManagerPermissionGates();

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
    $list->assertSee('td class="wb-table-actions wb-whitespace-nowrap"', false);
    $list->assertSee('class="wb-action-group wb-whitespace-nowrap"', false);

    $this->actingAs($user)
      ->post(route('admin.system.plugins.enable', 'webblocks-ui-manager'))
      ->assertRedirect(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $detail = $this->actingAs($user)->get(route('admin.system.plugins.show', 'webblocks-ui-manager'));

    $detail->assertOk();
    $detail->assertSeeText('Run Plugin Migrations');
    $detail->assertSee('class="wb-card-footer"', false);
    $detail->assertSee('Open Settings');
    $detail->assertDontSee('wb-btn-danger wb-w-full', false);
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

    $installPath = config('webblocks-plugins.install.root').'/webblocks-ui-manager/0.1.0';

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

    $installPath = config('webblocks-plugins.install.root').'/webblocks-ui-manager/0.1.0';

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

  private function defineWebBlocksUiManagerPermissionGates(): void
  {
    foreach (['view', 'manage', 'publish'] as $permission) {
      Gate::define('webblocks-ui-manager.'.$permission, fn (User $user): bool => $user->isSuperAdmin());
    }
  }

  private function dropWebBlocksUiManagerTables(): void
  {
    Schema::dropIfExists('webblocks_ui_manager_publish_runs');
    Schema::dropIfExists('webblocks_ui_manager_artifacts');
    Schema::dropIfExists('webblocks_ui_manager_releases');
  }
}
