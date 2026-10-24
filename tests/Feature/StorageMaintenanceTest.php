<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\Admin\MaintenanceCleanupController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalMaintenanceCleanupController;
use WebBlocks\Cms\Http\Requests\InternalContentApi\MaintenanceCleanupSettingsRequest;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Media\MediaTransformService;
use WebBlocks\Cms\Support\System\MaintenanceCleanup;
use WebBlocks\Cms\Support\System\MaintenanceCleanupResult;
use WebBlocks\Cms\Support\System\MaintenanceCleanupState;
use WebBlocks\Cms\Support\System\StorageUsageReport;
use WebBlocks\Cms\Support\System\SystemMaintenanceReports;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Tests\TestCase;

class StorageMaintenanceTest extends TestCase
{
  private string $root;

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->root = sys_get_temp_dir().'/wb-storage-'.bin2hex(random_bytes(8));
    $this->app->useStoragePath($this->root.'/storage');
    $this->app->usePublicPath($this->root.'/public');
    foreach (['public', 'backups', 'site-transfers'] as $disk) {
      config()->set('filesystems.disks.'.$disk, ['driver' => 'local', 'root' => $this->root.'/'.$disk, 'throw' => true]);
      Storage::forgetDisk($disk);
    }
  }

  protected function tearDown(): void
  {
    File::deleteDirectory($this->root);
    parent::tearDown();
  }

  #[Test]
  public function report_measures_actual_files_without_creating_missing_roots_or_persisting_state(): void
  {
    $reporter = app(StorageUsageReport::class);
    $empty = $reporter->overview();
    $this->assertSame('missing', $empty['categories']['backups']['status']);
    $this->assertDirectoryDoesNotExist($this->root.'/backups');
    foreach (['backups/a.zip' => 'backup', 'site-transfers/a.zip' => 'transfer', 'public/media/images/a.png' => 'original', 'public/media/transforms/1/old/a.png' => 'cache', 'public/media/transforms-other/a.png' => 'keep', 'storage/logs/app.log' => 'log'] as $path => $bytes) {
      File::ensureDirectoryExists(dirname($this->root.'/'.$path));
      File::put($this->root.'/'.$path, $bytes);
    }
    $result = $reporter->overview()['categories'];
    $this->assertSame(['files' => 1, 'bytes' => 6, 'status' => 'available'], $result['backups']);
    $this->assertSame(8, $result['transfer_packages']['bytes']);
    $this->assertSame(12, $result['public_uploads']['bytes']);
    $this->assertSame(2, $result['public_uploads']['files']);
    $this->assertSame(5, $result['media_variants']['bytes']);
    $this->assertSame(3, $result['logs']['bytes']);
    $this->assertSame([], app(MaintenanceCleanupState::class)->overview()['categories']);
    $this->assertStringNotContainsString($this->root, json_encode($result));
    symlink($this->root.'/backups/a.zip', $this->root.'/public/external.zip');
    $this->assertSame('partial', $reporter->overview()['categories']['public_uploads']['status']);
    $this->assertSame(12, $reporter->overview()['categories']['public_uploads']['bytes']);
  }

  #[Test]
  public function ninety_day_defaults_preserve_saved_policies_and_the_latest_fifty_revision_floor(): void
  {
    $settings = app(SystemSettings::class);
    $policy = $settings->maintenanceCleanupSettings();
    $this->assertSame(90, $policy['page_revision_days']);
    $this->assertSame(90, $policy['shared_slot_revision_days']);
    $this->assertSame(50, $policy['keep_latest_page_revisions']);
    $this->assertSame(50, $policy['keep_latest_shared_slot_revisions']);

    $site = Site::create(['name' => 'Synthetic', 'handle' => 'synthetic', 'is_primary' => true]);
    $page = Page::create(['site_id' => $site->id, 'title' => 'Synthetic', 'status' => 'draft']);
    $rows = [];
    foreach (range(1, 52) as $id) {
      $rows[] = ['id' => $id, 'page_id' => $page->id, 'site_id' => $site->id, 'snapshot_bytes' => 10, 'created_at' => now()->subDays(120)->addSeconds($id)->toDateTimeString()];
    }
    DB::table('wbcms_page_revisions')->insert($rows);
    $settings->save([SystemSettings::CLEANUP_PAGE_REVISION_DAYS => 365, SystemSettings::CLEANUP_SHARED_SLOT_REVISION_DAYS => 180]);
    $this->assertSame(365, $settings->maintenanceCleanupSettings()['page_revision_days']);
    $this->assertSame(180, $settings->maintenanceCleanupSettings()['shared_slot_revision_days']);
    $this->assertSame(0, app(MaintenanceCleanup::class)->previewPageRevisions()->candidateCount);
    $this->assertDatabaseCount('wbcms_page_revisions', 52);

    $settings->save([SystemSettings::CLEANUP_PAGE_REVISION_DAYS => 90]);
    $this->assertSame(2, app(MaintenanceCleanup::class)->previewPageRevisions()->candidateCount);
    $this->assertDatabaseCount('wbcms_page_revisions', 52);
    $result = app(MaintenanceCleanup::class)->run('page-revisions');
    $this->assertSame(2, $result->deletedCount);
    $this->assertDatabaseCount('wbcms_page_revisions', 50);
    $this->assertDatabaseHas('wbcms_page_revisions', ['id' => 3]);
    $this->assertDatabaseHas('wbcms_page_revisions', ['id' => 52]);
  }

  #[Test]
  public function automatic_categories_are_opt_in_and_preview_never_records_execution(): void
  {
    $settings = app(SystemSettings::class);
    $this->assertSame(array_fill_keys(MaintenanceCleanup::RUNNABLE, false), $settings->automaticMaintenanceSettings());
    $this->artisan('system:cleanup', ['category' => 'media-variants', '--scheduled' => true])->assertSuccessful();
    $this->assertDatabaseCount(MaintenanceCleanupState::TABLE, 0);
    $settings->save([SystemSettings::CLEANUP_AUTOMATIC_KEYS['media-variants'] => true]);
    $this->artisan('system:cleanup', ['category' => 'media-variants', '--scheduled' => true, '--dry-run' => true])->assertSuccessful();
    $this->assertDatabaseCount(MaintenanceCleanupState::TABLE, 0);
    $this->artisan('system:cleanup', ['category' => 'media-variants', '--scheduled' => true])->assertSuccessful();
    $this->assertDatabaseHas(MaintenanceCleanupState::TABLE, ['category' => 'media-variants', 'source' => 'scheduled', 'status' => 'completed']);
    $this->assertNotNull(DB::table(MaintenanceCleanupState::TABLE)->value('last_success_at'));
    $this->assertSame('unverified', app(MaintenanceCleanup::class)->overview()['automation']['scheduler']['scheduler_status']);
  }

  #[Test]
  public function scheduler_registers_daily_files_and_weekly_content_history_with_overlap_protection(): void
  {
    $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains((string) $event->command, 'system:cleanup'));
    $this->assertCount(5, $events);
    foreach ($events as $event) {
      $this->assertStringContainsString('--scheduled', $event->command);
      $this->assertTrue($event->withoutOverlapping);
      $this->assertTrue($event->onOneServer);
      $this->assertSame(str_contains($event->command, 'page-revisions') ? '30 4 * * 0' : (str_contains($event->command, 'shared-slot-revisions') ? '45 4 * * 0' : (str_contains($event->command, 'asset-revisions') ? '0 4 * * *' : (str_contains($event->command, 'media-variants') ? '10 4 * * *' : '20 4 * * *'))), $event->expression);
    }
  }

  #[Test]
  public function schema_and_overlap_fail_closed_and_execution_summaries_are_bounded(): void
  {
    $cleanup = app(MaintenanceCleanup::class);
    $lock = Cache::lock('cms:maintenance-cleanup', 60);
    $lock->get();
    $this->assertSame('busy', $cleanup->run('media-variants')->failures[0]['code']);
    $lock->release();
    foreach (range(1, 3) as $_) {
      $cleanup->run('media-variants');
    }
    $this->assertDatabaseCount(MaintenanceCleanupState::TABLE, 1);
    Schema::drop(MaintenanceCleanupState::TABLE);
    $this->assertSame('schema_missing', $cleanup->run('media-variants')->failures[0]['code']);
    $this->assertFalse($cleanup->overview()['automation']['ready']);
    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_10_23_120000_create_maintenance_cleanup_states.php';
    $migration->up();
    $migration->up();
    $this->assertTrue(app(MaintenanceCleanupState::class)->ready());
  }

  #[Test]
  public function temporary_cleanup_preserves_live_updates_recent_work_and_locked_catalog_downloads(): void
  {
    $old = now()->subDays(2)->getTimestamp();
    $update = storage_path('app/system-updates/old');
    File::ensureDirectoryExists($update);
    File::put($update.'/package.zip', 'old');
    touch($update.'/package.zip', $old);
    touch($update, $old);
    $recent = storage_path('app/system-updates/recent');
    File::ensureDirectoryExists($recent);
    File::put($recent.'/package.zip', 'new');
    touch($recent, $old);
    $catalog = storage_path('app/webblocks/tmp/catalog-installs');
    File::ensureDirectoryExists($catalog);
    File::put($catalog.'/catalog-plugin-old', 'download');
    File::put($catalog.'/unrelated.txt', 'keep');
    touch($catalog.'/catalog-plugin-old', $old);
    touch($catalog.'/unrelated.txt', $old);
    $lease = fopen($catalog.'/catalog-plugin-old', 'r');
    flock($lease, LOCK_SH);
    $updateLock = Cache::lock('system-updates:run', 60);
    $updateLock->get();
    try {
      $first = app(MaintenanceCleanup::class)->run('temporary-workspaces');
      $this->assertSame(0, $first->deletedCount);
      $this->assertFileExists($update.'/package.zip');
      $this->assertFileExists($catalog.'/catalog-plugin-old');
    } finally {
      fclose($lease);
      $updateLock->release();
    }
    $second = app(MaintenanceCleanup::class)->run('temporary-workspaces');
    $this->assertSame(2, $second->deletedCount);
    $this->assertSame(11, $second->deletedBytes);
    $this->assertSame(11, $second->candidateBytes);
    $this->assertFileExists($recent.'/package.zip');
    $this->assertFileExists($catalog.'/unrelated.txt');
    $this->assertDirectoryDoesNotExist($update);
  }

  #[Test]
  public function partial_variant_failures_only_credit_bytes_actually_deleted_and_keep_last_success(): void
  {
    $media = Media::create(['disk' => 'public', 'path' => 'media/original.png', 'filename' => 'original.png', 'kind' => 'image', 'size' => 8]);
    $service = Mockery::mock(MediaTransformService::class);
    $service->shouldReceive('pruneWithResult')->once()->andReturn(['deleted' => 1, 'bytes' => 3, 'failures' => ['Failed']]);
    $cleanup = Mockery::mock(MaintenanceCleanup::class, [app(SystemSettings::class), $service])->makePartial();
    $cleanup->shouldReceive('previewMediaVariants')->andReturn(new MaintenanceCleanupResult(2, 20));
    $state = app(MaintenanceCleanupState::class);
    $state->start('media-variants', 'manual');
    $state->finish('media-variants', new MaintenanceCleanupResult(0, 0));
    $successAt = DB::table(MaintenanceCleanupState::TABLE)->value('last_success_at');
    $result = $cleanup->run('media-variants');
    $this->assertSame(3, $result->deletedBytes);
    $this->assertSame(1, $result->deletedCount);
    $this->assertDatabaseHas(MaintenanceCleanupState::TABLE, ['status' => 'failed', 'last_success_at' => $successAt, 'failure_count' => 1]);
  }

  #[Test]
  public function api_validates_category_selection_and_preserves_omitted_flags(): void
  {
    $rules = (new MaintenanceCleanupSettingsRequest)->rules();
    $settings = app(SystemSettings::class);
    $payload = [...$settings->maintenanceCleanupSettings(), 'automatic' => ['media-variants' => true]];
    $this->assertTrue(validator($payload, $rules)->passes());
    $this->assertFalse(validator([...$payload, 'automatic' => ['logs' => true]], $rules)->passes());
    $this->assertFalse(validator([...$payload, 'automatic' => ['media-variants' => 'invalid']], $rules)->passes());
    $request = Mockery::mock(MaintenanceCleanupSettingsRequest::class);
    $request->shouldReceive('validated')->andReturn($payload);
    $response = app(InternalMaintenanceCleanupController::class)->update($request)->getData(true);
    $this->assertTrue($response['policy']['automatic']['media-variants']);
    unset($payload['automatic']);
    $request = Mockery::mock(MaintenanceCleanupSettingsRequest::class);
    $request->shouldReceive('validated')->andReturn($payload);
    $response = app(InternalMaintenanceCleanupController::class)->update($request)->getData(true);
    $this->assertTrue($response['policy']['automatic']['media-variants']);
    $this->assertArrayHasKey('storage_report', $response['overview']);
    $this->assertFalse($response['policy']['automatic']['page-revisions']);
  }

  #[Test]
  public function cleanup_panel_renders_measurements_flags_and_failure_outcomes_in_both_locales(): void
  {
    $controller = app(MaintenanceCleanupController::class);
    $state = app(MaintenanceCleanupState::class);
    $state->start('media-variants', 'scheduled');
    $state->finish('media-variants', new MaintenanceCleanupResult(2, 20, 1, 3, [['code' => 'failed']]));
    app(SystemMaintenanceReports::class)->refresh();
    foreach (['en', 'tr'] as $locale) {
      app()->setLocale($locale);
      $html = $controller->index()->render();
      $this->assertStringContainsString('name="automatic[media-variants]"', $html);
      $this->assertStringContainsString('name="automatic[page-revisions]"', $html);
      $this->assertStringContainsString('cleanup-media-variants-modal', $html);
      $this->assertStringNotContainsString('storage.report_title', $html);
      $this->assertStringNotContainsString($this->root, $html);
      $this->assertStringContainsString($locale === 'en' ? 'Storage report' : 'Depolama raporu', $html);
      $this->assertStringContainsString($locale === 'en' ? 'Failed' : 'Başarısız', $html);
      $dom = new \DOMDocument;
      @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
      $xpath = new \DOMXPath($dom);
      foreach (['page-revisions' => ['page_revision_days', 'keep_latest_page_revisions'], 'shared-slot-revisions' => ['shared_slot_revision_days', 'keep_latest_shared_slot_revisions']] as $category => $fields) {
        foreach ($fields as $field) {
          $this->assertSame(1, $xpath->query('//section[@role="group" and @id="cleanup-'.$category.'-policy"]//input[@name="'.$field.'"]')->length);
        }
        $action = $xpath->query('//td[contains(@class,"wb-table-actions")]/div[contains(@class,"wb-action-group")]/a[@href="'.route('admin.system.cleanup.index', ['tab' => 'settings']).'#cleanup-'.$category.'-policy"]')->item(0);
        $this->assertNotNull($action);
        $this->assertNotSame('', $action->getAttribute('aria-label'));
        $this->assertSame($action->getAttribute('aria-label'), $action->getAttribute('title'));
        $this->assertSame(1, $xpath->query('.//i[contains(@class,"wb-icon-settings") and @aria-hidden="true"]', $action)->length);
        $this->assertSame('', trim($action->textContent));
      }
      $transfer = $xpath->query('//td[contains(@class,"wb-table-actions")]/div[contains(@class,"wb-action-group")]/a[@href="'.route('admin.site-transfers.exports.index').'"]')->item(0);
      $this->assertNotNull($transfer);
      $this->assertNotSame('', $transfer->getAttribute('aria-label'));
      $this->assertSame(1, $xpath->query('.//i[contains(@class,"wb-icon-eye")]', $transfer)->length);
      $this->assertSame(0, $xpath->query('//button[@data-wb-toggle="modal" and starts-with(@data-wb-target,"#cleanup-")]')->length);
      $this->assertStringContainsString($locale === 'en' ? 'Nothing to clean' : 'Temizlenecek kayıt yok', $html);
      $this->assertStringContainsString($locale === 'en' ? 'Sundays at 04:30' : 'Pazar günleri 04:30', $html);

    }
  }

  #[Test]
  public function an_unavailable_media_disk_does_not_hide_other_storage_categories_or_claim_zero_candidates(): void
  {
    Media::create(['disk' => 'unconfigured', 'path' => 'media/original.png', 'filename' => 'original.png', 'kind' => 'image', 'size' => 8]);
    $overview = app(MaintenanceCleanup::class)->overview();
    $this->assertSame('partial', $overview['media_storage']['variant_measurement_status']);
    $this->assertSame('preview_unavailable', $overview['media_variants']->failures[0]['code']);
    $this->assertSame('missing', $overview['storage_report']['categories']['backups']['status']);
    $this->assertDatabaseCount(MaintenanceCleanupState::TABLE, 0);
  }

  #[Test]
  public function unexpected_execution_failure_is_recorded_without_leaking_exception_text(): void
  {
    $cleanup = Mockery::mock(MaintenanceCleanup::class, [app(SystemSettings::class), app(MediaTransformService::class)])->makePartial();
    $cleanup->shouldReceive('previewMediaVariants')->andThrow(new \RuntimeException('sensitive diagnostic'));
    $result = $cleanup->run('media-variants');
    $this->assertSame('failed', $result->failures[0]['code']);
    $summary = app(MaintenanceCleanupState::class)->overview();
    $this->assertSame('failed', $summary['categories']['media-variants']['status']);
    $this->assertNull($summary['categories']['media-variants']['last_success_at']);
    $this->assertStringNotContainsString('sensitive diagnostic', json_encode($summary));
  }
}
