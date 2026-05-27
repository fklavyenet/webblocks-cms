<?php

namespace Tests\Feature\Plugins;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Console\PublishWebBlocksUiReleaseCommand;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Models\WebBlocksUiPublishRun;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Models\WebBlocksUiRelease;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Support\WebBlocksUiReleasePreparer;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Support\WebBlocksUiReleasePublisher;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\WebBlocksUiManagerPlugin;
use WebBlocks\Cms\Support\Plugins\PluginHealthMonitor;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRouteRegistrar;

class WebBlocksUiManagerPublishWorkflowTest extends TestCase
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

  protected function tearDown(): void
  {
    File::deleteDirectory(public_path('cdn/webblocks-ui/v2.7.9'));
    File::deleteDirectory(public_path('cdn/webblocks-ui/v2.8.0'));

    if (File::isDirectory(public_path('cdn/webblocks-ui')) && count(File::files(public_path('cdn/webblocks-ui'))) === 0 && count(File::directories(public_path('cdn/webblocks-ui'))) === 0) {
      File::deleteDirectory(public_path('cdn/webblocks-ui'));
    }

    if (File::isDirectory(public_path('cdn')) && count(File::files(public_path('cdn'))) === 0 && count(File::directories(public_path('cdn'))) === 0) {
      File::deleteDirectory(public_path('cdn'));
    }

    parent::tearDown();
  }

  #[Test]
  public function dry_run_reports_writes_without_creating_cdn_files(): void
  {
    $release = $this->preparedRelease();

    $run = app(WebBlocksUiReleasePublisher::class)->dryRun($release->version);

    $this->assertSame(WebBlocksUiPublishRun::MODE_DRY_RUN, $run->mode);
    $this->assertSame(WebBlocksUiPublishRun::STATUS_SUCCEEDED, $run->status);
    $this->assertSame(['write', 'write', 'write', 'write'], array_column($run->operations, 'action'));
    $this->assertFileDoesNotExist(public_path('cdn/webblocks-ui/v2.7.9/webblocks-ui.css'));
    $this->assertDatabaseHas('webblocks_ui_manager_publish_runs', [
      'release_id' => $release->id,
      'mode' => WebBlocksUiPublishRun::MODE_DRY_RUN,
      'status' => WebBlocksUiPublishRun::STATUS_SUCCEEDED,
    ]);
  }

  #[Test]
  public function publish_writes_artifacts_manifest_and_is_idempotent(): void
  {
    $release = $this->preparedRelease();

    $firstRun = app(WebBlocksUiReleasePublisher::class)->publish($release->version);
    $secondRun = app(WebBlocksUiReleasePublisher::class)->publish($release->version);

    $this->assertSame(WebBlocksUiPublishRun::STATUS_SUCCEEDED, $firstRun->status);
    $this->assertSame(WebBlocksUiPublishRun::STATUS_SUCCEEDED, $secondRun->status);
    $this->assertSame(['skip', 'skip', 'skip', 'skip'], array_column($secondRun->operations, 'action'));
    $this->assertFileExists(public_path('cdn/webblocks-ui/v2.7.9/webblocks-ui.css'));
    $this->assertFileExists(public_path('cdn/webblocks-ui/v2.7.9/webblocks-icons.css'));
    $this->assertFileExists(public_path('cdn/webblocks-ui/v2.7.9/webblocks-ui.js'));
    $this->assertFileExists(public_path('cdn/webblocks-ui/v2.7.9/manifest.json'));
    $this->assertDatabaseHas('webblocks_ui_manager_releases', [
      'version' => 'v2.7.9',
      'status' => WebBlocksUiRelease::STATUS_PUBLISHED,
    ]);
  }

  #[Test]
  public function publish_blocks_existing_target_checksum_mismatches(): void
  {
    $release = $this->preparedRelease();
    File::ensureDirectoryExists(public_path('cdn/webblocks-ui/v2.7.9'));
    File::put(public_path('cdn/webblocks-ui/v2.7.9/webblocks-ui.css'), 'different');

    $run = app(WebBlocksUiReleasePublisher::class)->publish($release->version);

    $this->assertSame(WebBlocksUiPublishRun::STATUS_BLOCKED, $run->status);
    $this->assertStringContainsString('different checksum', $run->message);
    $this->assertDatabaseHas('webblocks_ui_manager_releases', [
      'version' => 'v2.7.9',
      'status' => WebBlocksUiRelease::STATUS_BLOCKED,
    ]);
  }

  #[Test]
  public function publish_blocks_missing_expected_dist_files(): void
  {
    $artifact = $this->putTrackedPublicSiteFile('site/testing/webblocks-ui.css', 'body{}');
    $release = app(WebBlocksUiReleasePreparer::class)->prepare('v2.7.9', [$artifact])['release'];

    $run = app(WebBlocksUiReleasePublisher::class)->dryRun($release->version);

    $this->assertSame(WebBlocksUiPublishRun::STATUS_BLOCKED, $run->status);
    $this->assertStringContainsString('missing expected WebBlocks UI dist file', $run->message);
  }

  #[Test]
  public function publish_blocks_target_path_version_mismatches_and_traversal(): void
  {
    $release = $this->preparedRelease();
    $release->forceFill(['cdn_base_path' => 'cdn/webblocks-ui/v2.8.0'])->save();

    $run = app(WebBlocksUiReleasePublisher::class)->dryRun($release->version);

    $this->assertSame(WebBlocksUiPublishRun::STATUS_BLOCKED, $run->status);
    $this->assertStringContainsString('Release CDN path must be [cdn/webblocks-ui/v2.7.9]', $run->message);
  }

  #[Test]
  public function publish_blocks_source_paths_that_escape_project_root(): void
  {
    $release = $this->preparedRelease();
    $artifact = $release->artifacts()->first();
    $unsafePath = tempnam(sys_get_temp_dir(), 'webblocks-ui-');
    File::put($unsafePath, 'body{}');
    $artifact->forceFill([
      'source_path' => $unsafePath,
      'checksum_sha256' => hash_file('sha256', $unsafePath),
    ])->save();

    try {
      $run = app(WebBlocksUiReleasePublisher::class)->dryRun($release->version);

      $this->assertSame(WebBlocksUiPublishRun::STATUS_BLOCKED, $run->status);
      $this->assertStringContainsString('source path must stay inside the project root', $run->message);
    } finally {
      @unlink($unsafePath);
    }
  }

  #[Test]
  public function command_supports_dry_run_and_publish_modes(): void
  {
    $release = $this->preparedRelease();
    $command = app(PublishWebBlocksUiReleaseCommand::class);
    $command->setLaravel($this->app);

    $dryRunTester = new CommandTester($command);
    $dryRunExit = $dryRunTester->execute([
      'version' => $release->version,
      '--dry-run' => true,
    ]);

    $publishTester = new CommandTester($command);
    $publishExit = $publishTester->execute([
      'version' => $release->version,
    ]);

    $this->assertSame(0, $dryRunExit);
    $this->assertSame(0, $publishExit);
    $this->assertStringContainsString('Mode: dry-run', $dryRunTester->getDisplay());
    $this->assertStringContainsString('Mode: publish', $publishTester->getDisplay());
    $this->assertFileExists(public_path('cdn/webblocks-ui/v2.7.9/manifest.json'));
  }

  #[Test]
  public function admin_publish_actions_are_permission_gated(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-ui-manager', true);
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    $release = $this->preparedRelease();
    $this->definePluginPermissionGates();
    $superAdmin = User::factory()->superAdmin()->create();
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)
      ->post(route('webblocks.plugins.webblocks_ui_manager.releases.publish.dry-run', $release))
      ->assertForbidden();

    $show = $this->actingAs($superAdmin)->get(route('webblocks.plugins.webblocks_ui_manager.releases.show', ['release' => $release, 'modal' => 'publish']));
    $show->assertOk();
    $show->assertSee('Dry Run Publish');
    $show->assertSee('Publish Release');

    $this->actingAs($superAdmin)
      ->post(route('webblocks.plugins.webblocks_ui_manager.releases.publish.dry-run', $release))
      ->assertRedirect(route('webblocks.plugins.webblocks_ui_manager.releases.show', $release));
  }

  #[Test]
  public function health_reports_cdn_readiness_and_latest_published_release(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-ui-manager', true);
    $release = $this->preparedRelease();
    app(WebBlocksUiReleasePublisher::class)->publish($release->version);
    $plugin = app(PluginRegistry::class)->get('webblocks-ui-manager');

    $health = app(PluginHealthMonitor::class)->healthFor($plugin);

    $this->assertSame('healthy', $health->status);
    $this->assertStringContainsString('Latest published WebBlocks UI release: v2.7.9', $health->message);
  }

  #[Test]
  public function disabled_plugin_publish_routes_remain_absent_and_admin_cms_boundaries_hold(): void
  {
    $registry = new PluginRegistry(['webblocks-ui-manager' => false]);
    $registry->register(WebBlocksUiManagerPlugin::definition());

    $this->assertSame([], $registry->enabled());
    $this->assertSame([], $registry->menuItems());

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
  public function incompatible_plugin_publish_routes_remain_absent_even_when_configured_enabled(): void
  {
    $registry = new PluginRegistry(['webblocks-ui-manager' => true]);
    $registry->register(WebBlocksUiManagerPlugin::definition()->requiresCms('>=99.0.0'));

    $this->assertFalse($registry->isEnabled('webblocks-ui-manager'));
    $this->assertSame([], $registry->enabled());
    $this->assertSame([], $registry->menuItems());
  }

  #[Test]
  public function package_boundary_uses_package_publish_classes_and_views(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-ui-manager', true);
    $this->app->forgetInstance(PluginRegistry::class);
    app(PluginRegistry::class);

    $this->assertTrue(class_exists(PublishWebBlocksUiReleaseCommand::class));
    $this->assertTrue(class_exists(WebBlocksUiReleasePublisher::class));
    $this->assertTrue(view()->exists('webblocks-cms::plugins.webblocks-ui-manager.releases.show'));
    $this->assertFileDoesNotExist(base_path('app/Plugins/WebBlocksUiManager/Support/WebBlocksUiReleasePublisher.php'));
    $this->assertFileDoesNotExist(base_path('app/Http/Controllers/WebBlocksUiReleasePublishController.php'));
  }

  private function preparedRelease(): WebBlocksUiRelease
  {
    $artifacts = [
      $this->putTrackedPublicSiteFile('site/testing/dist/webblocks-ui.css', 'body { color: red; }'),
      $this->putTrackedPublicSiteFile('site/testing/dist/webblocks-icons.css', '.wb-icon {}'),
      $this->putTrackedPublicSiteFile('site/testing/dist/webblocks-ui.js', 'console.log("wb");'),
    ];

    return app(WebBlocksUiReleasePreparer::class)
      ->prepare('v2.7.9', $artifacts)['release'];
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
