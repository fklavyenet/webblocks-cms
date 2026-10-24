<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Queries\SystemHealthQuery;
use WebBlocks\Cms\Queries\SystemHealthSiteQuery;
use WebBlocks\Cms\Support\System\BackupArchiveValidation;
use WebBlocks\Cms\Support\System\BackupRestoreArchiveInspector;
use WebBlocks\Cms\Support\System\BackupRetentionPlan;
use WebBlocks\Cms\Support\System\MaintenanceCleanup;
use WebBlocks\Cms\Support\System\SystemBackupCleanup;
use WebBlocks\Cms\Support\System\SystemMaintenanceReports;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\System\UploadedSystemBackupManager;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;
use ZipArchive;

class SystemMaintenanceReportsTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function setUp(): void
  {
    parent::setUp();
    if (! class_exists('App\\Models\\User')) {
      class_alias(MaintenanceTestUser::class, 'App\\Models\\User');
    }
    $this->withoutMiddleware(RedirectIfNotInstalled::class);
    $this->actingAs($this->cmsActor(User::forceCreate(['name' => 'Synthetic operator', 'email' => 'operator@example.test', 'password' => 'unused', 'role' => 'super_admin', 'is_active' => true])));
    Storage::fake('backups');
    Storage::fake('public');
    config()->set('filesystems.disks.backups', ['driver' => 'local', 'root' => Storage::disk('backups')->path('')]);
  }

  private function archive(int $days = 40): SystemBackup
  {
    $name = bin2hex(random_bytes(8)).'.zip';
    $zip = new ZipArchive;
    $zip->open(Storage::disk('backups')->path($name), ZipArchive::CREATE);
    $zip->addFromString('manifest.json', '{"backup_id":1}');
    $zip->addFromString('database/database.sql', 'CREATE TABLE synthetic (id INTEGER);');
    $zip->close();
    $backup = SystemBackup::create(['type' => 'manual', 'status' => 'completed', 'includes_database' => true, 'includes_uploads' => false, 'archive_disk' => 'backups', 'archive_path' => $name, 'archive_filename' => $name, 'finished_at' => now()->subDays($days)]);
    $backup->forceFill(['created_at' => now()->subDays($days)])->save();

    return $backup;
  }

  private function forbidExpensiveReads(): void
  {
    $this->app->instance(BackupRestoreArchiveInspector::class, Mockery::mock(BackupRestoreArchiveInspector::class)->shouldNotReceive('inspect')->getMock());
    $this->app->instance(MaintenanceCleanup::class, Mockery::mock(MaintenanceCleanup::class)->shouldNotReceive('overview')->getMock());
    $this->app->instance(SystemHealthSiteQuery::class, Mockery::mock(SystemHealthSiteQuery::class)->shouldNotReceive('rows')->getMock());
  }

  #[Test]
  public function cold_panel_gets_never_run_reports_or_sync_queue_work_and_settings_remain_editable(): void
  {
    $this->archive();
    Cache::flush();
    $this->forbidExpensiveReads();
    $this->get(route('admin.dashboard'))->assertOk()->assertSee('Report not available yet.')->assertDontSee('No attention needed');
    $this->get(route('admin.system.backups.index'))->assertOk()->assertSee('data-wb-maintenance-report="missing"', false)->assertSee('name="retention_mode"', false);
    $this->get(route('admin.system.cleanup.index', ['tab' => 'settings']))->assertOk()->assertSee('data-wb-maintenance-report="missing"', false)->assertSee('name="page_revision_days"', false);
  }

  #[Test]
  public function missing_reports_leave_both_cleanup_panels_with_an_explanation_and_refresh_action(): void
  {
    Cache::flush();
    $this->forbidExpensiveReads();
    foreach (['report', 'actions'] as $tab) {
      $response = $this->get(route('admin.system.cleanup.index', ['tab' => $tab]))->assertOk();
      $document = new \DOMDocument;
      @$document->loadHTML($response->getContent());
      $xpath = new \DOMXPath($document);
      $panel = $xpath->query('//*[@id="cleanup-'.$tab.'-panel"]')->item(0);
      $this->assertNotNull($panel);
      $this->assertFalse($panel->hasAttribute('hidden'));
      $this->assertStringContainsString(__('webblocks-cms::maintenance_reports.cleanup_'.$tab.'_unavailable'), $panel->textContent);
      $this->assertSame(1, $xpath->query('.//form[@action="'.route('admin.system.health.refresh').'"]//input[@name="cleanup_tab" and @value="'.$tab.'"]', $panel)->length);
      $this->assertSame(1, $xpath->query('.//button[@data-wb-busy]', $panel)->length);
      $response->assertDontSee('data-wb-target="#cleanup-', false)->assertSee('name="page_revision_days"', false);
    }
  }

  #[Test]
  public function manual_refresh_confirms_success_and_keeps_the_site_filter(): void
  {
    $site = Site::create(['name' => 'Synthetic', 'handle' => 'synthetic']);
    $response = $this->post(route('admin.system.health.refresh'), ['site_id' => $site->id]);
    $response->assertRedirect(route('admin.system.health.index', ['site_id' => $site->id]))
      ->assertSessionHas('status', __('webblocks-cms::maintenance_reports.refresh_completed'));
    $this->assertSame('fresh', app(SystemMaintenanceReports::class)->read()['status']);
    $this->get($response->headers->get('Location'))->assertOk()
      ->assertSee('Checks and maintenance reports refreshed.')
      ->assertSee('data-wb-busy-label="Refreshing checks…"', false);
  }

  #[Test]
  public function cleanup_refresh_returns_to_the_requested_panel_with_the_completed_report(): void
  {
    foreach (['report', 'actions'] as $tab) {
      $response = $this->post(route('admin.system.health.refresh'), ['cleanup_tab' => $tab]);
      $response->assertRedirect(route('admin.system.cleanup.index', ['tab' => $tab]))
        ->assertSessionHas('status', __('webblocks-cms::maintenance_reports.refresh_completed'));
      $this->get($response->headers->get('Location'))->assertOk()
        ->assertSee('id="cleanup-'.$tab.'-panel"', false)
        ->assertSee('data-wb-maintenance-report="fresh"', false)
        ->assertDontSee(__('webblocks-cms::maintenance_reports.cleanup_'.$tab.'_unavailable'))
        ->assertSee('Storage report');
    }
  }

  #[Test]
  public function a_busy_refresh_shows_a_notice_instead_of_claiming_success(): void
  {
    app(SystemHealthQuery::class)->refreshSnapshot();
    $reports = app(SystemMaintenanceReports::class);
    $lock = Cache::lock($reports->cacheKey().':refresh', 900);
    $this->assertTrue($lock->get());
    $this->forbidExpensiveReads();
    try {
      foreach ([[], ['cleanup_tab' => 'actions']] as $parameters) {
        $response = $this->post(route('admin.system.health.refresh'), $parameters);
        $response->assertSessionHas('maintenance_refresh_pending', true)->assertSessionMissing('status');
        $this->get($response->headers->get('Location'))->assertOk()
          ->assertSee('Another report refresh is already in progress.')
          ->assertSee('role="status"', false)
          ->assertDontSee('Checks and maintenance reports refreshed.');
      }
    } finally {
      $lock->release();
    }
  }

  #[Test]
  public function cleanup_refresh_failures_remain_visible_in_the_requested_panel(): void
  {
    $this->app->instance(SystemMaintenanceReports::class, Mockery::mock(SystemMaintenanceReports::class)->makePartial()->shouldReceive('refresh')->once()->andThrow(new RuntimeException('SENTINEL_PRIVATE'))->getMock());
    $response = $this->post(route('admin.system.health.refresh'), ['cleanup_tab' => 'report']);
    $response->assertRedirect(route('admin.system.cleanup.index', ['tab' => 'report']))->assertSessionHasErrors('health');
    $this->get($response->headers->get('Location'))->assertOk()
      ->assertSee(__('webblocks-cms::maintenance_reports.refresh_failed'))
      ->assertDontSee('SENTINEL_PRIVATE');
  }

  #[Test]
  public function refresh_rejects_unknown_cleanup_destinations_before_doing_work(): void
  {
    $this->app->instance(SystemMaintenanceReports::class, Mockery::mock(SystemMaintenanceReports::class)->shouldNotReceive('refresh')->getMock());
    $this->postJson(route('admin.system.health.refresh'), ['cleanup_tab' => 'https://example.test'])->assertUnprocessable()->assertJsonValidationErrors('cleanup_tab');
  }

  #[Test]
  public function every_admin_language_has_all_maintenance_report_messages(): void
  {
    $english = require dirname(__DIR__, 2).'/resources/lang/en/maintenance_reports.php';
    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      $catalog = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/maintenance_reports.php';
      $this->assertSame(array_keys($english), array_keys($catalog));
      foreach ($catalog as $message) {
        $this->assertNotSame('', trim($message));
      }
    }
  }

  #[Test]
  public function warm_and_expired_reports_are_shared_without_reopening_sql_streams(): void
  {
    $this->archive();
    $reports = app(SystemMaintenanceReports::class);
    $this->assertTrue($reports->refresh());
    $this->assertSame('fresh', $reports->read()['status']);
    $this->forbidExpensiveReads();
    foreach ([0, 301] as $seconds) {
      if ($seconds) {
        $this->travel($seconds)->seconds();
      }
      $state = $seconds ? 'stale' : 'fresh';
      $this->get(route('admin.dashboard'))->assertOk()->assertSee('data-wb-system-health-summary', false);
      $this->get(route('admin.system.backups.index'))->assertOk()->assertSee('data-wb-maintenance-report="'.$state.'"', false);
      $this->get(route('admin.system.cleanup.index'))->assertOk()->assertSee('data-wb-maintenance-report="'.$state.'"', false)->assertSee('Storage report');
      $this->assertSame($state, app(SystemHealthQuery::class)->dashboardSummary()['status']);
    }
    $this->travelBack();
  }

  #[Test]
  public function incomplete_validation_is_explicit_and_can_never_produce_cleanup_candidates(): void
  {
    app(SystemSettings::class)->save([SystemSettings::BACKUP_LIFECYCLE_KEYS['manual_enabled'] => true, SystemSettings::BACKUP_LIFECYCLE_KEYS['keep_latest_manual'] => 1]);
    $this->archive(80);
    $this->archive(40);
    $preview = app(SystemBackupCleanup::class)->preview(verifyArchives: false);
    $this->assertSame([], $preview->candidateIds);
    $this->assertSame(2, $preview->overview['validation_pending']);
    $this->assertSame(1, app(BackupArchiveValidation::class)->validatePending(1));
    $this->assertSame(1, app(SystemBackupCleanup::class)->preview(false)->overview['validation_pending']);
    $this->assertSame(1, app(BackupArchiveValidation::class)->validatePending(1));
    $this->assertSame(0, app(SystemBackupCleanup::class)->preview(false)->overview['validation_pending']);
  }

  #[Test]
  public function a_replaced_archive_invalidates_provenance_and_full_cleanup_rechecks_recovery_floors(): void
  {
    app(SystemSettings::class)->save([SystemSettings::BACKUP_LIFECYCLE_KEYS['manual_enabled'] => true, SystemSettings::BACKUP_LIFECYCLE_KEYS['keep_latest_manual'] => 1]);
    $old = $this->archive(80);
    $latest = $this->archive(40);
    $reports = app(SystemMaintenanceReports::class);
    $reports->refresh();
    $this->assertSame([$old->id], $reports->read()['backup']->candidateIds);
    $path = Storage::disk('backups')->path($latest->archive_path);
    file_put_contents($path.'.replacement', 'invalid ZIP');
    rename($path.'.replacement', $path);
    $this->assertSame('pending', app(BackupArchiveValidation::class)->status(app(BackupArchiveValidation::class)->records([$latest->id])->get($latest->id), $path));
    $result = app(SystemBackupCleanup::class)->run(force: true);
    $this->assertSame([$latest->id], $result->deletedIds); // The broken archive is eligible; the remaining valid floor is protected.
    Storage::disk('backups')->assertExists($old->archive_path);
    $this->assertSame('minimum_count', collect(app(BackupRetentionPlan::class)->overview()['items'])->firstWhere('id', $old->id)['reason']);
  }

  #[Test]
  public function mutations_mark_the_last_report_stale_and_a_refresh_is_serialized(): void
  {
    $this->archive();
    $reports = app(SystemMaintenanceReports::class);
    $reports->refresh();
    $this->archive();
    $this->assertSame('stale', $reports->read()['status']);
    $this->assertNotNull($reports->read()['backup']);
    $lock = Cache::lock($reports->cacheKey().':refresh', 900);
    $this->assertTrue($lock->get());
    try {
      $this->forbidExpensiveReads();
      $this->assertFalse($reports->refresh());
    } finally {
      $lock->release();
    }
  }

  #[Test]
  public function cache_read_and_write_failure_never_recomputes_the_health_snapshot(): void
  {
    $this->app->instance(SystemHealthSiteQuery::class, Mockery::mock(SystemHealthSiteQuery::class)->shouldReceive('rows')->once()->andReturn([])->getMock());
    Cache::shouldReceive('get')->andReturnUsing(static fn (string $key, $default = null) => str_ends_with($key, ':generation') ? '' : throw new RuntimeException('Synthetic cache read failure'));
    Cache::shouldReceive('put')->once()->andThrow(new RuntimeException('Synthetic cache write failure'));
    $report = app(SystemHealthQuery::class)->report();
    $this->assertSame([], $report['sites']);
    $this->assertSame('unknown', $report['categories']['storage']['status']);
    $this->assertSame('missing', app(SystemHealthQuery::class)->dashboardSummary()['status']);
  }

  #[Test]
  public function refresh_failure_preserves_the_previous_report_and_releases_the_lease(): void
  {
    $reports = app(SystemMaintenanceReports::class);
    $reports->refresh();
    $before = $reports->read();
    $this->app->instance(MaintenanceCleanup::class, Mockery::mock(MaintenanceCleanup::class)->shouldReceive('overview')->once()->andThrow(new RuntimeException('Synthetic scan failure'))->getMock());
    try {
      $reports->refresh();
      $this->fail('Refresh should fail.');
    } catch (RuntimeException) {
      $this->assertEquals($before, $reports->read());
    }
    $lock = Cache::lock($reports->cacheKey().':refresh', 900);
    $this->assertTrue($lock->get());
    $lock->release();
  }

  #[Test]
  public function missing_validation_schema_never_reverts_to_full_inspection_during_reporting(): void
  {
    $this->archive();
    Schema::drop(BackupArchiveValidation::TABLE);
    $this->app->instance(BackupRestoreArchiveInspector::class, Mockery::mock(BackupRestoreArchiveInspector::class)->shouldNotReceive('inspect')->getMock());
    $this->assertSame(1, app(SystemBackupCleanup::class)->preview(false)->overview['validation_pending']);
    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_10_24_120000_create_backup_archive_validations.php';
    $migration->up();
    $migration->up();
    $this->assertTrue(Schema::hasTable(BackupArchiveValidation::TABLE));
  }

  #[Test]
  public function ordinary_requests_read_the_last_report_while_a_refresh_owns_the_lease(): void
  {
    $this->archive();
    $reports = app(SystemMaintenanceReports::class);
    $reports->refresh();
    $this->travel(301)->seconds();
    $cleanup = app(MaintenanceCleanup::class);
    $observations = [];
    $this->app->instance(MaintenanceCleanup::class, Mockery::mock(MaintenanceCleanup::class)->shouldReceive('overview')->once()->andReturnUsing(function () use ($cleanup, $reports, &$observations): array {
      // This hook runs inside the actual refresh, before its new snapshot is published.
      $lock = Cache::lock($reports->cacheKey().':refresh', 900);
      $this->assertFalse($lock->get());
      foreach (['dashboard' => 'admin.dashboard', 'backups' => 'admin.system.backups.index', 'cleanup' => 'admin.system.cleanup.index'] as $page => $route) {
        $start = hrtime(true);
        $response = $this->get(route($route))->assertOk();
        $observations[$page] = (hrtime(true) - $start) / 1e6;
        if ($page !== 'dashboard') {
          $response->assertSee('data-wb-maintenance-report="stale"', false);
        }
      }

      return $cleanup->overview();
    })->getMock());
    $this->assertTrue($reports->refresh());
    $this->assertSame('fresh', $reports->read()['status']);
    $this->assertSame('fresh', app(SystemHealthQuery::class)->dashboardSummary()['status']);
    if (getenv('CMS_REPORT_PROFILE') === '1') {
      fwrite(STDERR, 'REFRESH_REQUEST_MS='.json_encode($observations).PHP_EOL);
    }
    $this->travelBack();
  }

  #[Test]
  public function malformed_cached_reports_are_unavailable_and_never_rebuild_in_the_request(): void
  {
    $reports = app(SystemMaintenanceReports::class);
    Cache::put($reports->cacheKey(), ['checked_at' => now()->timestamp, 'generation' => '', 'backup' => 'invalid', 'cleanup' => []], 300);
    $this->forbidExpensiveReads();
    $this->assertSame('missing', $reports->read()['status']);
    $this->get(route('admin.system.cleanup.index'))->assertOk()->assertSee('Report not available yet.');
  }

  #[Test]
  public function a_mutation_during_refresh_cannot_publish_an_apparently_fresh_report(): void
  {
    $reports = app(SystemMaintenanceReports::class);
    $reports->refresh();
    $cleanup = app(MaintenanceCleanup::class);
    $this->app->instance(MaintenanceCleanup::class, Mockery::mock(MaintenanceCleanup::class)->shouldReceive('overview')->once()->andReturnUsing(function () use ($cleanup): array {
      app(SystemSettings::class)->save([SystemSettings::BACKUP_LIFECYCLE_KEYS['manual_enabled'] => true]);

      return $cleanup->overview();
    })->getMock());
    $reports->refresh();
    $this->assertSame('stale', $reports->read()['status']);
  }

  #[Test]
  public function a_mutation_during_health_measurement_keeps_the_dashboard_summary_stale(): void
  {
    $reports = app(SystemMaintenanceReports::class);
    $this->app->instance(SystemHealthSiteQuery::class, Mockery::mock(SystemHealthSiteQuery::class)->shouldReceive('rows')->once()->andReturnUsing(function () use ($reports): array {
      $reports->invalidate();

      return [];
    })->getMock());
    $health = app(SystemHealthQuery::class);
    $health->refreshSnapshot();
    $this->assertSame('stale', $health->dashboardSummary()['status']);
  }

  #[Test]
  public function uploaded_archives_record_typed_provenance_and_removing_a_backup_removes_its_provenance(): void
  {
    $source = $this->archive();
    $temporary = tempnam(sys_get_temp_dir(), 'synthetic-upload-');
    copy(Storage::disk('backups')->path($source->archive_path), $temporary);
    try {
      $upload = new UploadedFile($temporary, 'synthetic.zip', 'application/zip', null, true);
      $backup = app(UploadedSystemBackupManager::class)->import($upload);
      $record = app(BackupArchiveValidation::class)->records([$backup->id])->get($backup->id);
      $this->assertSame('valid', $record->status);
      $this->assertSame(hash_file('sha256', Storage::disk('backups')->path($backup->archive_path)), $record->sha256);
      $this->assertSame(BackupArchiveValidation::VERSION, (int) $record->validator_version);
      $backup->delete();
      $this->assertDatabaseMissing(BackupArchiveValidation::TABLE, ['backup_id' => $backup->id]);
    } finally {
      if (is_file($temporary)) {
        unlink($temporary);
      }
    }
  }

  #[Test]
  public function manual_refresh_failure_retains_the_site_filter_and_hides_private_error_text(): void
  {
    $site = Site::create(['name' => 'Synthetic', 'handle' => 'synthetic']);
    $this->app->instance(SystemMaintenanceReports::class, Mockery::mock(SystemMaintenanceReports::class)->makePartial()->shouldReceive('refresh')->once()->andThrow(new RuntimeException('SENTINEL_PRIVATE'))->getMock());
    $response = $this->post(route('admin.system.health.refresh', ['site_id' => $site->id]));
    $response->assertRedirect(route('admin.system.health.index', ['site_id' => $site->id]))->assertSessionHasErrors('health');
    $this->assertStringNotContainsString('SENTINEL_PRIVATE', session('errors')->first('health'));
  }

  #[Test]
  public function the_scheduled_refresh_command_uses_the_same_service_and_reports_failure(): void
  {
    $reports = Mockery::mock(SystemMaintenanceReports::class);
    $reports->shouldReceive('refresh')->once()->andReturn(true);
    $this->app->instance(SystemMaintenanceReports::class, $reports);
    $this->artisan('system:reports:refresh')->assertSuccessful();
    $reports->shouldReceive('refresh')->once()->andThrow(new RuntimeException('SENTINEL_PRIVATE'));
    $this->artisan('system:reports:refresh')->expectsOutput(__('webblocks-cms::maintenance_reports.refresh_failed'))->assertFailed();
    $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command ?? '', 'system:reports:refresh'));
    $this->assertCount(1, $events);
    $this->assertSame('*/5 * * * *', $events->first()->expression);
  }
}
