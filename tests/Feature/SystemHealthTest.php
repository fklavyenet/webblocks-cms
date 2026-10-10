<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\Admin\SystemHealthController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PublicSearchIndex;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Policies\SystemHealthPolicy;
use WebBlocks\Cms\Queries\SystemHealthQuery;
use WebBlocks\Cms\Queries\SystemHealthSiteQuery;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenAuthenticator;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\SiteNotifications\SchedulerHealth;
use WebBlocks\Cms\Support\SiteNotifications\SiteNotificationPolicy;
use WebBlocks\Cms\Support\System\MaintenanceCleanup;
use WebBlocks\Cms\Support\System\SystemHealthStatus;
use WebBlocks\Cms\Support\System\SystemUpdateInspector;
use WebBlocks\Cms\Support\System\Updates\AdminUpdateIndicator;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Tests\TestCase;

class SystemHealthTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('app.url', 'https://example.test');
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function defineRoutes($router): void
  {
    Route::get('/test-health', [SystemHealthController::class, 'index']);
  }

  protected function setUp(): void
  {
    parent::setUp();
    if (! class_exists('App\\Models\\User')) {
      class_alias(AuthenticatableUser::class, 'App\\Models\\User');
    }
  }

  private function user(bool $system = true): AuthenticatableUser
  {
    $user = Mockery::mock(AuthenticatableUser::class)->makePartial();
    $user->forceFill(['id' => 99, 'name' => 'Synthetic Operator', 'is_active' => true]);
    $user->shouldReceive('can')->andReturn(false)->byDefault();
    $user->shouldReceive('can')->with('access-system')->andReturn($system);

    return $user;
  }

  private function site(string $handle = 'northstar', bool $scheduled = true): Site
  {
    Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site = Site::create(['name' => ucfirst($handle), 'handle' => $handle, 'domain' => $handle.'.example.test', 'is_primary' => Site::count() === 0, 'notification_settings' => $scheduled ? SiteNotificationPolicy::DEFAULTS : SiteNotificationPolicy::LEGACY]);
    Page::create(['site_id' => $site->id, 'title' => 'Home', 'slug' => 'home', 'status' => 'published']);

    return $site;
  }

  private function report(?int $siteId = null): array
  {
    return app(SystemHealthQuery::class)->report($siteId);
  }

  #[Test]
  public function routes_keep_existing_tools_and_require_installation_authority(): void
  {
    foreach (['admin.system.health.index', 'admin.system.health.refresh', 'admin.system.information', 'admin.system.cleanup.index', 'admin.system.backups.index'] as $name) {
      $this->assertContains('can:access-system', Route::getRoutes()->getByName($name)->gatherMiddleware());
    }
    $this->assertContains('internal-api.capability:system-health.read', Route::getRoutes()->getByName('internal-content-api.system.health.show')->gatherMiddleware());
    $path = app(InternalApiDiscoveryController::class)->openapi()->getData(true)['paths']['/system/health']['get'];
    $this->assertSame('system-health.read', $path['x-required-capability']);
    $this->assertFalse($path['x-destructive'] ?? false);
  }

  #[Test]
  public function a_site_manager_cannot_open_the_health_screen(): void
  {
    $this->actingAs($this->user(false))->get('/test-health')->assertForbidden();
  }

  #[Test]
  public function the_screen_renders_summaries_and_preserves_the_old_menu_links(): void
  {
    $this->site();
    $this->actingAs($this->user());
    $this->get('/test-health')->assertOk()->assertSee('System Health')->assertSee('What needs attention')->assertSee('System details')->assertSee('https://cms.webblocksui.com/docs')->assertSee(route('admin.system.information'))->assertSee(route('admin.system.backups.index'))->assertDontSee('data-wb-nav-group-open="help"');
  }

  #[Test]
  public function reading_health_does_not_run_cleanup_change_backups_or_create_a_heartbeat(): void
  {
    $this->site();
    $backup = SystemBackup::create(['status' => 'running', 'type' => 'manual', 'started_at' => now()->subHours(2), 'created_at' => now()->subHours(2)]);
    $backup->timestamps = false;
    $backup->forceFill(['created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2)])->saveQuietly();
    $before = SystemSetting::count();
    $report = $this->report();
    $this->assertCount(1, $report['sites']);
    $this->assertSame('running', $backup->fresh()->status);
    $this->assertSame($before, SystemSetting::count());
    $this->assertNull(app(SchedulerHealth::class)->snapshot()['last_seen_at']);
    $this->assertContains('backup_stalled', array_column($report['categories']['backups']['checks'], 'message'));
  }

  #[Test]
  public function a_recent_successful_record_with_a_missing_archive_is_critical(): void
  {
    $this->site();
    SystemBackup::create(['status' => 'completed', 'type' => 'manual', 'finished_at' => now(), 'archive_path' => 'nonexistent-health-test.zip', 'archive_disk' => 'backups', 'error_message' => 'SENTINEL_PRIVATE', 'output' => 'SENTINEL_PRIVATE']);
    $report = $this->report();
    $this->assertSame('critical', $report['categories']['backups']['status']);
    $this->assertSame('healthy', $report['categories']['backups']['checks'][0]['status']);
    $this->assertStringNotContainsString('SENTINEL_PRIVATE', json_encode($report));
  }

  #[Test]
  public function filtering_sites_keeps_global_checks_and_excludes_other_site_issues(): void
  {
    $allowed = $this->site('northstar', false);
    $other = $this->site('harbor', true);
    $report = $this->report($allowed->id);
    $this->assertSame([$allowed->id], array_column($report['sites'], 'id'));
    $this->assertCount(2, $report['site_options']);
    $this->assertSame('not_applicable', $report['categories']['scheduling']['status']);
    $this->assertArrayHasKey('backups', $report['categories']);
    foreach ($report['issues'] as $issue) {
      $this->assertNotSame($other->id, $issue['site_id'] ?? null);
    }
    $this->assertSame('unknown', $this->report()['categories']['scheduling']['status']);
  }

  #[Test]
  public function scheduler_evidence_is_live_even_while_capacity_is_cached(): void
  {
    $this->site();
    $first = $this->report();
    $health = app(SchedulerHealth::class);
    $health->scheduledHeartbeat();
    $health->dispatchCompleted();
    $second = $this->report();
    $this->assertSame($first['checked_at'], $second['checked_at']);
    $this->assertSame('healthy', $second['categories']['scheduling']['status']);
    $this->travel(6)->minutes();
    $this->assertSame('warning', $this->report()['categories']['scheduling']['status']);
  }

  #[Test]
  public function expensive_site_scans_are_cached_and_refresh_only_invalidates_the_snapshot(): void
  {
    $mock = Mockery::mock(SystemHealthSiteQuery::class);
    $mock->shouldReceive('rows')->twice()->andReturn([]);
    $this->app->instance(SystemHealthSiteQuery::class, $mock);
    $this->report();
    $this->report();
    app(SystemHealthQuery::class)->refresh();
    $this->report();
  }

  #[Test]
  public function unavailable_checks_are_isolated_and_do_not_expose_exception_text(): void
  {
    $this->site();
    $this->mock(MaintenanceCleanup::class, fn ($mock) => $mock->shouldReceive('overview')->once()->andThrow(new \RuntimeException('SENTINEL_SECRET')));
    $report = $this->report();
    $this->assertSame('unknown', $report['categories']['storage']['status']);
    $this->assertCount(1, $report['sites']);
    $this->assertStringNotContainsString('SENTINEL_SECRET', json_encode($report));
  }

  #[Test]
  public function search_coverage_detects_missing_entries_without_treating_old_dates_as_failures(): void
  {
    $this->site();
    $rows = app(SystemHealthSiteQuery::class)->rows();
    $this->assertSame('healthy', $rows[0]['search']['status']);
    PublicSearchIndex::query()->update(['indexed_at' => now()->subYear()]);
    $this->assertSame('healthy', app(SystemHealthSiteQuery::class)->rows()[0]['search']['status']);
    PublicSearchIndex::query()->delete();
    $search = app(SystemHealthSiteQuery::class)->rows()[0]['search'];
    $this->assertSame('warning', $search['status']);
    $this->assertSame(1, $search['parameters']['missing']);
  }

  #[Test]
  public function missing_search_schema_is_unverified_instead_of_healthy(): void
  {
    $this->site();
    Schema::drop('wbcms_public_search_index');
    $this->assertSame('unknown', $this->report()['categories']['search']['status']);
  }

  #[Test]
  public function readiness_checks_do_not_create_probe_directories_or_fetch_releases(): void
  {
    $path = storage_path('health-test-workspace-'.uniqid());
    config(['webblocks-updates.installer.workspace_root' => basename($path)]);
    $checks = app(SystemUpdateInspector::class)->readOnlyChecks();
    $this->assertNotEmpty($checks);
    $this->assertDirectoryDoesNotExist($path);
    $this->assertNull(Cache::get(AdminUpdateIndicator::CACHE_KEY));
  }

  #[Test]
  public function release_status_uses_only_recent_cached_evidence(): void
  {
    Cache::put(AdminUpdateIndicator::CACHE_KEY, ['state' => 'up_to_date', 'checked_at' => now()->toIso8601String()]);
    $this->assertSame('healthy', $this->report()['categories']['updates']['checks'][0]['status']);
    app(SystemHealthQuery::class)->refresh();
    Cache::put(AdminUpdateIndicator::CACHE_KEY, ['state' => 'up_to_date', 'checked_at' => now()->subHours(2)->toIso8601String()]);
    $this->assertSame('unknown', $this->report()['categories']['updates']['checks'][0]['status']);
  }

  #[Test]
  public function api_authority_excludes_personal_scoped_inactive_and_unprivileged_tokens(): void
  {
    $token = new CmsApiToken(['token_type' => 'system', 'allowed_site_ids' => null, 'capabilities' => ['system-health.read']]);
    $token->setRelation('creator', $this->user());
    $policy = app(SystemHealthPolicy::class);
    $this->assertTrue($policy->readApi($token));
    $this->assertTrue(app(CmsApiTokenCapabilities::class)->has($token, 'system-health.read'));
    foreach ([['token_type' => 'personal'], ['allowed_site_ids' => []], ['allowed_site_ids' => [1]], ['revoked_at' => now()], ['expires_at' => now()->subMinute()]] as $override) {
      $candidate = clone $token;
      $candidate->forceFill($override);
      $this->assertFalse($policy->readApi($candidate));
      $this->assertFalse(app(CmsApiTokenCapabilities::class)->has($candidate, 'system-health.read'));
    }
    $token->creator->is_active = false;
    $this->assertFalse($policy->readApi($token));
    $token->setRelation('creator', $this->user(false));
    $this->assertFalse($policy->readApi($token));
  }

  #[Test]
  public function the_api_returns_safe_filtered_results_and_rejects_invalid_filters(): void
  {
    $site = $this->site();
    $token = new CmsApiToken(['token_type' => 'system', 'allowed_site_ids' => null, 'capabilities' => ['system-health.read']]);
    $token->setRelation('creator', $this->user());
    DB::table('users')->insert(['id' => 99, 'name' => 'Synthetic Operator', 'email' => 'operator@example.test', 'password' => 'unused']);
    $token->forceFill(['name' => 'Health reader', 'token_hash' => hash('sha256', 'synthetic-health-test'), 'token_preview' => 'synthetic', 'created_by_user_id' => 99])->save();
    $auth = Mockery::mock(CmsApiTokenAuthenticator::class);
    $auth->shouldReceive('authenticate')->andReturn($token);
    $this->app->instance(CmsApiTokenAuthenticator::class, $auth);
    $this->withoutMiddleware('install.required');
    $this->getJson('/webadmin/api/system/health?site_id='.$site->id)->assertOk()->assertJsonPath('health.selected_site_id', $site->id)->assertJsonPath('health.sites.0.id', $site->id);
    $this->getJson('/webadmin/api/system/health?site_id=999999')->assertUnprocessable();
    $token->allowed_site_ids = [$site->id];
    $this->getJson('/webadmin/api/system/health')->assertForbidden();
  }

  #[Test]
  public function every_admin_language_has_all_health_copy_and_statuses(): void
  {
    $english = require dirname(__DIR__, 2).'/resources/lang/en/system_health.php';
    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      $catalog = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/system_health.php';
      $this->assertSame(array_keys($english), array_keys($catalog));
      $this->assertSame(array_keys($english['messages']), array_keys($catalog['messages']));
      $this->assertEqualsCanonicalizing(array_keys(SystemHealthStatus::ORDER), array_keys($catalog['statuses']));
    }
  }
}
