<?php

namespace Tests\Feature\Plugins;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Console\PrepareWebBlocksUiReleaseCommand;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Console\PublishWebBlocksUiReleaseCommand;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Models\WebBlocksUiRelease;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Support\WebBlocksUiArtifactManifestBuilder;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Support\WebBlocksUiReleasePreparer;
use WebBlocks\Cms\Support\Plugins\PluginCommandRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginHealthMonitor;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRouteRegistrar;

class WebBlocksUiManagerPluginTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    $this->installWebBlocksUiManagerPluginForTest();
    Artisan::call('migrate', [
      '--path' => 'plugins/webblocks-ui-manager/database/migrations',
      '--realpath' => false,
    ]);
    Artisan::call('migrate', [
      '--path' => 'plugins/webblocks-ui-manager/database/migrations/updates',
      '--realpath' => false,
    ]);
  }

  #[Test]
  public function pilot_plugin_is_registered_disabled_by_default_with_owned_contributions(): void
  {
    $registry = app(PluginRegistry::class);
    $plugin = $registry->get('webblocks-ui-manager');

    $this->assertNotNull($plugin);
    $this->assertSame('WebBlocks UI Manager', $plugin->labelText());
    $this->assertSame('0.1.0', $plugin->versionText());
    $this->assertSame('^1.32', $plugin->requiredCmsVersion());
    $this->assertSame('webblocks_ui_manager', $plugin->settingsNamespaceValue());
    $this->assertSame('webblocks_ui_manager_', $plugin->databasePrefixValue());
    $this->assertFalse($registry->isEnabled('webblocks-ui-manager'));
    $this->assertSame([], $registry->menuItems());
    $this->assertSame([], $registry->dashboardWidgets());
    $this->assertSame([], $registry->systemCards());
    $this->assertSame([], (new PluginCommandRegistrar($registry))->enabledCommands());
    $this->assertSame('unavailable', app(PluginHealthMonitor::class)->healthFor($plugin)->status);
  }

  #[Test]
  public function enabled_plugin_exposes_menu_permissions_commands_widgets_and_settings(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-ui-manager', true);
    $this->definePluginPermissionGates();

    $registry = app(PluginRegistry::class);
    $plugin = $registry->get('webblocks-ui-manager');
    $user = User::factory()->superAdmin()->create();

    $this->assertTrue($registry->isEnabled('webblocks-ui-manager'));
    $this->assertArrayHasKey('webblocks-ui-manager.view', $plugin->permissionsList());
    $this->assertSame('webblocks.plugins.webblocks_ui_manager', $plugin->routeNamePrefix());
    $this->assertSame('/webadmin/plugins/webblocks-ui-manager', $plugin->adminRoutePrefix());
    $this->assertSame([
      PrepareWebBlocksUiReleaseCommand::class,
      PublishWebBlocksUiReleaseCommand::class,
    ], (new PluginCommandRegistrar($registry))->enabledCommands());
    $this->assertSame('webblocks-ui-manager', $registry->dashboardWidgets($user)[0]->pluginHandle());
    $this->assertSame('webblocks-ui-manager', $registry->systemCards($user)[0]->pluginHandle());
    $this->assertNotNull($plugin->settingsDefinition());
  }

  #[Test]
  public function release_tables_are_plugin_owned_and_available(): void
  {
    $this->assertTrue(Schema::hasTable('webblocks_ui_manager_releases'));
    $this->assertTrue(Schema::hasTable('webblocks_ui_manager_artifacts'));
    $this->assertTrue(Schema::hasTable('webblocks_ui_manager_publish_runs'));
    $this->assertTrue(Schema::hasColumns('webblocks_ui_manager_releases', [
      'version',
      'cdn_base_path',
      'manifest',
      'prepared_at',
    ]));
    $this->assertTrue(Schema::hasColumns('webblocks_ui_manager_artifacts', [
      'release_id',
      'handle',
      'target_path',
      'checksum_sha256',
      'metadata',
    ]));
    $this->assertTrue(Schema::hasColumns('webblocks_ui_manager_publish_runs', [
      'release_id',
      'mode',
      'status',
      'target_root',
      'target_release_path',
      'operations',
    ]));
  }

  #[Test]
  public function enabled_plugin_routes_register_under_plugin_namespace_only(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-ui-manager', true);

    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    Route::getRoutes()->refreshNameLookups();

    $route = Route::getRoutes()->getByName('webblocks.plugins.webblocks_ui_manager.releases.index');

    $this->assertNotNull($route);
    $this->assertSame('webadmin/plugins/webblocks-ui-manager/releases', $route?->uri());
    $this->assertNotNull(Route::getRoutes()->getByName('admin.dashboard'));

    $adminRoutes = collect(Route::getRoutes()->getRoutes())
      ->filter(fn ($route): bool => $route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/'))
      ->values();
    $cmsRoutes = collect(Route::getRoutes()->getRoutes())
      ->filter(fn ($route): bool => $route->uri() === 'cms' || str_starts_with($route->uri(), 'cms/'))
      ->values();

    $this->assertCount(0, $adminRoutes);
    $this->assertCount(0, $cmsRoutes);
  }

  #[Test]
  public function disabled_plugin_routes_are_absent(): void
  {
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    Route::getRoutes()->refreshNameLookups();

    $this->assertNull(Route::getRoutes()->getByName('webblocks.plugins.webblocks_ui_manager.releases.index'));
    $this->assertNull(Route::getRoutes()->getByName('webblocks.plugins.webblocks_ui_manager.settings.edit'));
  }

  #[Test]
  public function enabled_plugin_admin_pages_render_for_permitted_super_admins(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-ui-manager', true);
    $this->definePluginPermissionGates();
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();

    $release = WebBlocksUiRelease::query()->create([
      'version' => 'v2.7.9',
      'label' => 'WebBlocks UI v2.7.9',
      'status' => WebBlocksUiRelease::STATUS_DRAFT,
      'cdn_base_path' => 'cdn/webblocks-ui/v2.7.9',
    ]);

    $user = User::factory()->superAdmin()->create();

    $index = $this->actingAs($user)->get(route('webblocks.plugins.webblocks_ui_manager.releases.index'));
    $index->assertOk();
    $index->assertSee('WebBlocks UI v2.7.9');

    $show = $this->actingAs($user)->get(route('webblocks.plugins.webblocks_ui_manager.releases.show', $release));
    $show->assertOk();
    $show->assertSee('cdn/webblocks-ui/v2.7.9');
  }

  #[Test]
  public function manifest_builder_generates_checksums_without_publishing(): void
  {
    $artifact = $this->putTrackedPublicSiteFile('site/testing/webblocks-ui.css', 'body { color: red; }');
    $release = WebBlocksUiRelease::query()->create([
      'version' => 'v2.7.9',
      'status' => WebBlocksUiRelease::STATUS_DRAFT,
      'cdn_base_path' => 'cdn/webblocks-ui/v2.7.9',
    ]);

    $manifest = app(WebBlocksUiArtifactManifestBuilder::class)->prepare($release, [$artifact]);

    $this->assertSame('webblocks-ui-manager', $manifest['plugin']);
    $this->assertSame('v2.7.9', $manifest['version']);
    $this->assertSame(hash_file('sha256', $artifact), $manifest['artifacts'][0]['checksum_sha256']);
    $this->assertFileDoesNotExist(public_path('cdn/webblocks-ui/v2.7.9/manifest.json'));
  }

  #[Test]
  public function manifest_builder_rejects_duplicate_artifact_handles(): void
  {
    $first = $this->putTrackedPublicSiteFile('site/testing/dist/webblocks-ui.css', 'body { color: red; }');
    $second = $this->putTrackedPublicSiteFile('site/testing/other/webblocks-ui.css', 'body { color: blue; }');
    $release = WebBlocksUiRelease::query()->create([
      'version' => 'v2.7.9',
      'status' => WebBlocksUiRelease::STATUS_DRAFT,
      'cdn_base_path' => 'cdn/webblocks-ui/v2.7.9',
    ]);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Artifact handle [webblocks-ui.css] is already present');

    app(WebBlocksUiArtifactManifestBuilder::class)->prepare($release, [$first, $second]);
  }

  #[Test]
  public function preparer_stores_release_artifact_and_manifest_metadata(): void
  {
    $artifact = $this->putTrackedPublicSiteFile('site/testing/webblocks-ui.js', 'console.log("wb");');

    $result = app(WebBlocksUiReleasePreparer::class)->prepare('v2.7.9', [$artifact]);
    $release = $result['release'];

    $this->assertSame(WebBlocksUiRelease::STATUS_PREPARED, $release->status);
    $this->assertSame('v2.7.9', $release->manifest['version']);
    $this->assertDatabaseHas('webblocks_ui_manager_artifacts', [
      'release_id' => $release->id,
      'handle' => 'webblocks-ui.js',
      'checksum_sha256' => hash_file('sha256', $artifact),
    ]);
  }

  #[Test]
  public function health_reports_release_metadata_and_local_cdn_manifest_readiness(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-ui-manager', true);

    $registry = app(PluginRegistry::class);
    $plugin = $registry->get('webblocks-ui-manager');
    $artifact = $this->putTrackedPublicSiteFile('site/testing/webblocks-ui-health.js', 'console.log("health");');
    $targetDirectory = public_path('cdn/webblocks-ui/v2.7.9');

    try {
      $emptyHealth = app(PluginHealthMonitor::class)->healthFor($plugin);
      $this->assertSame('unknown', $emptyHealth->status);

      app(WebBlocksUiReleasePreparer::class)->prepare('v2.7.9', [$artifact], writeManifest: true);

      $healthy = app(PluginHealthMonitor::class)->healthFor($plugin);
      $this->assertSame('healthy', $healthy->status);
      $this->assertFileExists($targetDirectory.'/manifest.json');
    } finally {
      File::deleteDirectory($targetDirectory);
      if (File::isDirectory(public_path('cdn/webblocks-ui')) && count(File::files(public_path('cdn/webblocks-ui'))) === 0 && count(File::directories(public_path('cdn/webblocks-ui'))) === 0) {
        File::deleteDirectory(public_path('cdn/webblocks-ui'));
      }
      if (File::isDirectory(public_path('cdn')) && count(File::files(public_path('cdn'))) === 0 && count(File::directories(public_path('cdn'))) === 0) {
        File::deleteDirectory(public_path('cdn'));
      }
    }
  }

  #[Test]
  public function prepare_release_command_records_local_artifact_metadata_when_enabled(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-ui-manager', true);

    $artifact = $this->putTrackedPublicSiteFile('site/testing/webblocks-icons.css', '.wb-icon {}');
    $command = app(PrepareWebBlocksUiReleaseCommand::class);
    $command->setLaravel($this->app);
    $tester = new CommandTester($command);

    $exitCode = $tester->execute([
      'version' => 'v2.7.9',
      '--artifact' => [$artifact],
    ]);

    $this->assertSame(0, $exitCode);
    $this->assertDatabaseHas('webblocks_ui_manager_releases', [
      'version' => 'v2.7.9',
      'status' => WebBlocksUiRelease::STATUS_PREPARED,
    ]);
  }

  #[Test]
  public function package_boundary_uses_package_plugin_classes_and_views(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-ui-manager', true);
    $this->app->forgetInstance(PluginRegistry::class);
    app(PluginRegistry::class);

    $this->assertTrue(class_exists(WebBlocksUiRelease::class));
    $this->assertTrue(class_exists(PrepareWebBlocksUiReleaseCommand::class));
    $this->assertTrue(class_exists(PublishWebBlocksUiReleaseCommand::class));
    $this->assertTrue(view()->exists('webblocks-cms::plugins.webblocks-ui-manager.releases.index'));
    $this->assertFileDoesNotExist(base_path('app/Plugins/WebBlocksUiManager/WebBlocksUiManagerPlugin.php'));
    $this->assertFileDoesNotExist(base_path('app/Http/Controllers/WebBlocksUiReleaseController.php'));
    $this->assertFileDoesNotExist(resource_path('views/plugins/webblocks-ui-manager/releases/index.blade.php'));
  }

  private function definePluginPermissionGates(): void
  {
    foreach (['view', 'manage', 'publish'] as $permission) {
      Gate::define('webblocks-ui-manager.'.$permission, fn (User $user): bool => $user->isSuperAdmin());
    }
  }

  private function installWebBlocksUiManagerPluginForTest(): void
  {
    $root = storage_path('framework/testing/plugins/'.str()->uuid());
    config()->set('webblocks-plugins.install.root', $root);

    File::ensureDirectoryExists($root.'/webblocks-ui-manager/0.1.0');
    File::copyDirectory(base_path('plugins/webblocks-ui-manager'), $root.'/webblocks-ui-manager/0.1.0');

    $this->app->forgetInstance(PluginRegistry::class);
  }
}
