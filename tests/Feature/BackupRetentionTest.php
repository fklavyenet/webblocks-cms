<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Actions\SystemBackups\UpdateBackupRetention;
use WebBlocks\Cms\Http\Controllers\Admin\MaintenanceCleanupController;
use WebBlocks\Cms\Http\Controllers\Admin\SystemBackupController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalBackupCleanupController;
use WebBlocks\Cms\Http\Requests\Admin\CreateSystemBackupRequest;
use WebBlocks\Cms\Http\Requests\Admin\SystemBackupRetentionRequest;
use WebBlocks\Cms\Http\Requests\InternalContentApi\BackupCleanupSettingsRequest;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Support\System\BackupOperations;
use WebBlocks\Cms\Support\System\BackupRestoreArchiveInspector;
use WebBlocks\Cms\Support\System\BackupRetentionPlan;
use WebBlocks\Cms\Support\System\BackupRetentionSchema;
use WebBlocks\Cms\Support\System\MaintenanceCleanupState;
use WebBlocks\Cms\Support\System\SystemBackupBulkDeleter;
use WebBlocks\Cms\Support\System\SystemBackupCleanup;
use WebBlocks\Cms\Support\System\SystemBackupManager;
use WebBlocks\Cms\Support\System\SystemMaintenanceReports;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Tests\TestCase;
use ZipArchive;

class BackupRetentionTest extends TestCase
{
  use RefreshDatabase;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    if (! class_exists('App\\Models\\User')) {
      class_alias(User::class, 'App\\Models\\User');
    }
    config()->set('webblocks-cms.routes.admin', true);
    Storage::fake('backups');
    config()->set('filesystems.disks.backups', ['driver' => 'local', 'root' => Storage::disk('backups')->path('')]);
  }

  private function archive(string $type = 'manual', int $days = 40): SystemBackup
  {
    $path = bin2hex(random_bytes(5)).'.zip';
    $zip = new ZipArchive;
    $zip->open(Storage::disk('backups')->path($path), ZipArchive::CREATE);
    $zip->addFromString('manifest.json', '{"backup_id":1}');
    $zip->addFromString('database/database.sql', 'CREATE TABLE synthetic (id INTEGER);');
    $zip->close();
    $backup = SystemBackup::create(['type' => $type, 'status' => 'completed', 'includes_database' => true, 'includes_uploads' => true, 'archive_disk' => 'backups', 'archive_path' => $path, 'archive_filename' => $path, 'archive_size_bytes' => 1, 'finished_at' => now()->subDays($days)]);
    $backup->forceFill(['created_at' => now()->subDays($days)])->save();

    return $backup;
  }

  private function setPolicy(array $values): void
  {
    $settings = [];
    foreach ($values as $key => $value) {
      $settings[SystemSettings::BACKUP_LIFECYCLE_KEYS[$key]] = $value;
    }
    app(SystemSettings::class)->save($settings);
  }

  #[Test]
  public function defaults_are_safe_and_saved_retention_is_preserved(): void
  {
    $policy = app(SystemSettings::class)->backupCleanupSettings();
    $this->assertFalse($policy['manual_enabled']);
    $this->assertSame(14, $policy['restore_safety_days']);
    $this->assertSame(3, $policy['keep_latest_manual']);
    $this->assertSame(2, $policy['keep_latest_restore_safety']);
    $this->assertSame(0, $policy['budget_gb']);
    app(SystemSettings::class)->save([SystemSettings::BACKUP_CLEANUP_RESTORE_SAFETY_DAYS => 30]);
    $this->assertSame(30, app(SystemSettings::class)->backupCleanupSettings()['restore_safety_days']);
    $manual = $this->archive();
    $this->assertSame([], app(SystemBackupCleanup::class)->preview()->candidateIds);
    $this->assertDatabaseHas('wbcms_system_backups', ['id' => $manual->id]);
  }

  #[Test]
  public function only_available_valid_archives_count_toward_the_minimum_and_age_applies_together(): void
  {
    $this->setPolicy(['manual_enabled' => true]);
    $oldest = $this->archive('manual', 80);
    $valid = [$this->archive('manual', 70), $this->archive('manual', 60), $this->archive('manual', 50)];
    $broken = $this->archive('manual', 45);
    Storage::disk('backups')->put($broken->archive_path, 'broken ZIP');
    $missing = $this->archive('manual', 44);
    Storage::disk('backups')->delete($missing->archive_path);
    $young = $this->archive('manual', 1);
    $plan = app(BackupRetentionPlan::class)->overview();
    $decisions = collect($plan['items'])->keyBy('id');
    $this->assertSame('eligible', $decisions[$oldest->id]['reason']);
    $this->assertSame('minimum_count', $decisions[$valid[1]->id]['reason']);
    $this->assertSame('minimum_count', $decisions[$valid[2]->id]['reason']);
    $this->assertSame('minimum_count', $decisions[$young->id]['reason']);
    $this->assertSame('unavailable', $decisions[$missing->id]['reason']);
    $this->assertSame('eligible', $decisions[$broken->id]['reason']);
    $this->assertNotContains($young->id, app(SystemBackupCleanup::class)->preview()->candidateIds);
  }

  #[Test]
  public function protection_is_enforced_for_automatic_single_and_bulk_deletion_even_with_stale_models(): void
  {
    $this->setPolicy(['manual_enabled' => true, 'keep_latest_manual' => 1]);
    $old = $this->archive('manual', 60);
    $this->archive('manual', 40);
    $stale = clone $old;
    app(UpdateBackupRetention::class)->execute($old, ['is_protected' => true, 'retention_mode' => 'policy']);
    $this->assertSame([], app(SystemBackupCleanup::class)->run(force: true)->deletedIds);
    $bulk = app(SystemBackupBulkDeleter::class)->deleteSelected([$old->id]);
    $this->assertSame(0, $bulk->deletedCount());
    $this->assertStringContainsString(__('webblocks-cms::backup-retention.protected_error'), implode(' ', $bulk->failureMessages()));
    try {
      app(SystemBackupManager::class)->deleteBackupRecord($stale, allowRunning: true);
      $this->fail('Protection was bypassed.');
    } catch (RuntimeException $exception) {
      $this->assertSame(__('webblocks-cms::backup-retention.protected_error'), $exception->getMessage());
    }
    Storage::disk('backups')->assertExists($old->archive_path);
    app(UpdateBackupRetention::class)->execute($old, ['is_protected' => false, 'retention_mode' => 'policy']);
    $this->assertSame([$old->id], app(SystemBackupCleanup::class)->run(force: true)->deletedIds);
  }

  #[Test]
  public function explicit_expiration_is_required_for_uploads_and_does_not_override_floors_or_protection(): void
  {
    $uploaded = $this->archive('uploaded', 100);
    $this->assertSame([], app(SystemBackupCleanup::class)->preview()->candidateIds);
    app(UpdateBackupRetention::class)->execute($uploaded, ['is_protected' => true, 'retention_mode' => 'expires', 'expires_at' => now()->subDay()]);
    $this->assertSame([], app(SystemBackupCleanup::class)->preview()->candidateIds);
    app(UpdateBackupRetention::class)->execute($uploaded, ['is_protected' => false, 'retention_mode' => 'expires', 'expires_at' => now()->subDay()]);
    $this->assertSame([$uploaded->id], app(SystemBackupCleanup::class)->preview()->candidateIds);
    $manual = $this->archive();
    app(UpdateBackupRetention::class)->execute($manual, ['is_protected' => false, 'retention_mode' => 'expires', 'expires_at' => now()->subDay()]);
    $this->assertNotContains($manual->id, app(SystemBackupCleanup::class)->preview()->candidateIds);
  }

  #[Test]
  public function budget_counts_unknown_files_reports_remaining_excess_and_never_weakens_retention(): void
  {
    $this->setPolicy(['budget_gb' => 0.01, 'manual_enabled' => true]);
    $backup = $this->archive();
    $backup->update(['is_protected' => true]);
    $handle = fopen(Storage::disk('backups')->path('unregistered.zip'), 'w');
    ftruncate($handle, 12000000);
    fclose($handle);
    $plan = app(BackupRetentionPlan::class)->overview();
    $this->assertSame('exceeded', $plan['storage']['budget_status']);
    $this->assertGreaterThan(12000000, $plan['storage']['total_bytes']);
    $this->assertSame(0, $plan['storage']['eligible_bytes']);
    $this->assertGreaterThan(0, $plan['storage']['remaining_over_budget_bytes']);
    $this->assertSame([], app(SystemBackupCleanup::class)->run(force: true)->deletedIds);
    Storage::disk('backups')->assertExists('unregistered.zip');
    $this->assertSame(filesize(Storage::disk('backups')->path($backup->archive_path)), $plan['storage']['protected_bytes']);
  }

  #[Test]
  public function deletion_accounts_for_actual_bytes_and_preview_is_read_only(): void
  {
    $old = $this->archive('uploaded');
    $old->update(['retention_mode' => 'expires', 'expires_at' => now()->subDay()]);
    $this->assertFalse(Storage::disk('backups')->exists('.backup-operations.lock'));
    $preview = app(SystemBackupCleanup::class)->preview();
    $this->assertFalse(Storage::disk('backups')->exists('.backup-operations.lock'));
    $size = filesize(Storage::disk('backups')->path($old->archive_path));
    $this->assertSame($size, $preview->candidateBytes);
    $run = app(SystemBackupCleanup::class)->run(force: true);
    $this->assertSame($size, $run->deletedBytes);
    $this->assertSame([$old->id], $run->deletedIds);
    Storage::disk('backups')->assertMissing($old->archive_path);
    $failed = $this->archive('uploaded');
    $failed->update(['retention_mode' => 'expires', 'expires_at' => now()->subDay()]);
    $failedSize = filesize(Storage::disk('backups')->path($failed->archive_path));
    Event::listen('eloquent.deleting: '.SystemBackup::class, fn () => throw new RuntimeException('Synthetic record-delete failure'));
    try {
      $partial = app(SystemBackupCleanup::class)->run(force: true);
      $this->assertSame([], $partial->deletedIds);
      $this->assertCount(1, $partial->failures);
      $this->assertSame($failedSize, $partial->deletedBytes);
      $this->assertDatabaseHas('wbcms_system_backups', ['id' => $failed->id]);
      Storage::disk('backups')->assertMissing($failed->archive_path);
    } finally {
      Event::forget('eloquent.deleting: '.SystemBackup::class);
    }
  }

  #[Test]
  public function a_second_operation_cannot_enter_the_lease_and_schema_absence_blocks_cleanup(): void
  {
    app(BackupOperations::class)->run(function (): void {
      try {
        (new BackupOperations)->run(fn () => $this->fail('Concurrent operation entered.'));
        $this->fail('Lease was ignored.');
      } catch (RuntimeException $exception) {
        $this->assertSame(__('webblocks-cms::backup-retention.busy'), $exception->getMessage());
      }
    });
    $backup = $this->archive('uploaded');
    Schema::table('wbcms_system_backups', fn ($table) => $table->dropColumn('is_protected'));
    $result = app(SystemBackupCleanup::class)->run(force: true);
    $this->assertNotEmpty($result->failures);
    $this->assertSame([], $result->deletedIds);
    Storage::disk('backups')->assertExists($backup->archive_path);
    BackupRetentionSchema::migrate();
    BackupRetentionSchema::migrate();
    $this->assertTrue(BackupRetentionSchema::ready());
  }

  #[Test]
  public function cleanup_tabs_separate_information_settings_and_actions_and_preserve_deep_links(): void
  {
    app(SystemMaintenanceReports::class)->refresh(100);
    request()->query->set('tab', 'settings');
    app(SystemMaintenanceReports::class)->refresh(100);
    $html = app(MaintenanceCleanupController::class)->index()->render();
    $dom = new \DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new \DOMXPath($dom);
    $this->assertSame(0, $xpath->query('//fieldset|//legend')->length);
    $this->assertSame(3, $xpath->query('//div[@role="tablist"]/button')->length);
    $this->assertSame(1, $xpath->query('//*[@id="cleanup-settings-panel" and not(@hidden)]//form')->length);
    $this->assertSame(1, $xpath->query('//*[@id="cleanup-report-panel" and @hidden]')->length);
    $this->assertSame(0, $xpath->query('//*[@id="cleanup-report-panel"]//button[@data-wb-toggle="modal"]')->length);
    $this->assertStringContainsString('tab=settings#cleanup-page-revisions-policy', $html);
    $this->assertStringContainsString('name="_cleanup_tab" value="settings"', $html);
  }

  #[Test]
  public function backup_list_and_details_render_real_retention_controls(): void
  {
    $backup = $this->archive();
    $index = app(SystemBackupController::class)->index()->render();
    $this->assertStringContainsString('create-backup-retention-modal', $index);
    $this->assertStringContainsString('name="is_protected"', $index);
    $this->assertStringContainsString('name="retention_mode"', $index);
    $show = app(SystemBackupController::class)->show($backup)->render();
    $this->assertStringContainsString('id="backup-retention"', $show);
    $this->assertStringContainsString('name="expires_at"', $show);
    $this->assertStringContainsString(route('admin.system.backups.retention.update', $backup), $show);
  }

  #[Test]
  public function shared_archives_are_not_counted_twice_or_deleted_through_an_unprotected_alias(): void
  {
    $first = $this->archive('uploaded');
    $first->update(['retention_mode' => 'expires', 'expires_at' => now()->subDay()]);
    $alias = $first->replicate();
    $alias->is_protected = true;
    $alias->save();
    $plan = app(BackupRetentionPlan::class)->overview();
    $this->assertSame('shared_archive', collect($plan['items'])->firstWhere('id', $first->id)['reason']);
    $this->assertSame(0, $plan['storage']['eligible_bytes']);
    app(SystemBackupManager::class)->deleteBackupRecord($first);
    Storage::disk('backups')->assertExists($alias->archive_path);
    $alias->update(['is_protected' => false]);
    $second = $alias->replicate();
    $second->save();
    $size = filesize(Storage::disk('backups')->path($alias->archive_path));
    $this->assertSame($size, app(SystemBackupCleanup::class)->preview()->candidateBytes);
    $result = app(SystemBackupCleanup::class)->run(force: true);
    $this->assertCount(2, $result->deletedIds);
    $this->assertSame($size, $result->deletedBytes);
  }

  #[Test]
  public function reports_do_not_create_missing_storage_roots(): void
  {
    $backup = $this->archive();
    $missingRoot = Storage::disk('backups')->path('absent-root');
    config()->set('filesystems.disks.backups.root', $missingRoot);
    Storage::forgetDisk('backups');
    $plan = app(BackupRetentionPlan::class)->overview();
    $this->assertDirectoryDoesNotExist($missingRoot);
    $this->assertSame('missing', $plan['storage']['status']);
    $this->assertSame('unavailable', collect($plan['items'])->firstWhere('id', $backup->id)['reason']);
  }

  #[Test]
  public function expired_failed_records_remain_cleanable_and_outcomes_are_bounded_and_truthful(): void
  {
    $backup = $this->archive('content_apply');
    $backup->update(['status' => 'failed']);
    $size = filesize(Storage::disk('backups')->path($backup->archive_path));
    $this->assertSame([], app(MaintenanceCleanupState::class)->overview()['categories']);
    $preview = app(SystemBackupCleanup::class)->preview();
    $this->assertSame([$backup->id], $preview->candidateIds);
    $this->assertSame([], app(MaintenanceCleanupState::class)->overview()['categories']);
    app(SystemBackupCleanup::class)->run(force: true, source: 'scheduled');
    $state = app(MaintenanceCleanupState::class)->overview()['categories']['backups'];
    $this->assertSame('scheduled', $state['source']);
    $this->assertSame('completed', $state['status']);
    $this->assertSame($size, $state['deleted_bytes']);
    app(SystemBackupCleanup::class)->run(force: true);
    $this->assertDatabaseCount('wbcms_maintenance_cleanup_states', 1);
  }

  #[Test]
  public function settings_and_retention_requests_validate_new_values_and_preserve_omitted_settings(): void
  {
    $rules = (new BackupCleanupSettingsRequest)->rules();
    $policy = app(SystemSettings::class)->backupCleanupSettings();
    $policy['budget_gb'] = null;
    $this->assertTrue(validator($policy, $rules)->passes());
    $this->assertFalse(validator([...$policy, 'budget_gb' => -1], $rules)->passes());
    $this->assertFalse(validator([...$policy, 'keep_latest_manual' => 0], $rules)->passes());
    $this->setPolicy(['manual_enabled' => true, 'budget_gb' => 2.5]);
    $legacy = array_intersect_key($policy, array_flip(['enabled', 'pre_update_days', 'keep_latest_pre_update', 'restore_safety_days', 'content_apply_days']));
    $request = \Mockery::mock(BackupCleanupSettingsRequest::class);
    $request->shouldReceive('validated')->andReturn($legacy);
    $response = app(InternalBackupCleanupController::class)->update($request)->getData(true);
    $this->assertTrue($response['policy']['manual_enabled']);
    $this->assertSame(2.5, $response['policy']['budget_gb']);
    $retention = (new SystemBackupRetentionRequest)->rules();
    $this->assertFalse(validator(['is_protected' => false, 'retention_mode' => 'expires'], $retention)->passes());
    $this->assertFalse(validator(['is_protected' => false, 'retention_mode' => 'unknown'], $retention)->passes());
    $this->assertTrue(validator(['is_protected' => true, 'retention_mode' => 'never'], $retention)->passes());
    $create = (new CreateSystemBackupRequest)->rules();
    $this->assertTrue(validator([], $create)->passes());
    $this->assertFalse(validator(['is_protected' => true], $create)->passes());
  }

  #[Test]
  public function reason_summary_reconciles_records_files_and_bytes_and_renders_in_cleanup(): void
  {
    foreach (range(1, 6) as $days) {
      $this->archive('pre_update', $days);
    }
    foreach (range(1, 4) as $index) {
      $this->archive('manual');
    }
    $this->archive('uploaded')->update(['is_protected' => true]);
    $this->archive('uploaded')->update(['retention_mode' => 'expires', 'expires_at' => now()->subDay()]);
    $this->archive('uploaded');
    $missing = $this->archive('uploaded');
    Storage::disk('backups')->delete($missing->archive_path);
    $this->archive('uploaded')->update(['status' => 'running']);
    Storage::disk('backups')->put('unregistered.zip', str_repeat('x', 4096));
    Storage::disk('backups')->put('.backup-operations.lock', '');

    $plan = app(BackupRetentionPlan::class)->overview();
    $summary = $plan['reason_summary'];
    $groups = collect($summary['groups'])->keyBy('reason');
    $expectedCounts = ['minimum_count' => 8, 'retained' => 1, 'manual_disabled' => 1, 'protected' => 1, 'eligible' => 1, 'never' => 1, 'unavailable' => 1, 'running' => 1];
    $this->assertCount(8, $groups);
    foreach ($expectedCounts as $reason => $count) {
      $this->assertSame($count, $groups[$reason]['record_count'], $reason);
    }
    $this->assertSame(15, $summary['record_count']);
    $this->assertSame(16, $summary['file_count']);
    $this->assertSame(['file_count' => 2, 'bytes' => 4096], $summary['other_files']);
    $this->assertSame($summary['bytes'], $groups->sum('bytes') + $summary['other_files']['bytes']);
    $this->assertSame($summary['file_count'], $groups->sum('file_count') + $summary['other_files']['file_count']);
    $this->assertSame($plan['storage']['eligible_bytes'], $groups['eligible']['bytes']);
    $this->assertSame(0, $groups['unavailable']['file_count']);
    $this->assertSame(1, $groups['running']['file_count']);
    $this->assertDatabaseCount('wbcms_maintenance_cleanup_states', 0);
    $this->assertSame($summary, app(InternalBackupCleanupController::class)->show()->getData(true)['preview']['overview']['reason_summary']);
    $this->assertStringNotContainsString(Storage::disk('backups')->path(''), json_encode($summary, JSON_UNESCAPED_SLASHES));

    app(SystemMaintenanceReports::class)->refresh(100);
    $html = app(MaintenanceCleanupController::class)->index()->render();
    $dom = new \DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new \DOMXPath($dom);
    $table = '//*[@id="cleanup-report-panel"]//table[@aria-labelledby="backup-retention-reasons"]';
    $this->assertSame(8, $xpath->query($table.'/tbody/tr[th and td[1] != "—"]')->length);
    $this->assertSame('15', $xpath->query($table.'/tfoot/tr/td[1]')->item(0)->textContent);
    $this->assertSame('16', $xpath->query($table.'/tfoot/tr/td[2]')->item(0)->textContent);
    $this->assertStringContainsString(__('webblocks-cms::backup-retention.summary_rule_help'), $html);
    $this->assertStringContainsString(__('webblocks-cms::backup-retention.summary_other_help'), $html);
  }

  #[Test]
  public function reason_summary_assigns_shared_physical_bytes_once_to_the_strongest_reason(): void
  {
    $original = $this->archive('uploaded');
    $protected = $original->replicate();
    $protected->is_protected = true;
    $protected->save();
    $expired = $original->replicate();
    $expired->retention_mode = 'expires';
    $expired->expires_at = now()->subDay();
    $expired->save();
    $plan = app(BackupRetentionPlan::class)->overview();
    $summary = $plan['reason_summary'];
    $groups = collect($summary['groups'])->keyBy('reason');
    $size = filesize(Storage::disk('backups')->path($original->archive_path));
    $this->assertSame(3, $summary['record_count']);
    $this->assertSame(1, $summary['file_count']);
    $this->assertSame($size, $summary['bytes']);
    $this->assertSame($size, $groups['protected']['bytes']);
    $this->assertSame(0, $groups['never']['bytes']);
    $this->assertSame(0, $groups['shared_archive']['bytes']);
    $this->assertSame(['file_count' => 0, 'bytes' => 0], $summary['other_files']);
    $this->assertSame([], app(SystemBackupCleanup::class)->preview()->candidateIds);
  }

  #[Test]
  public function running_archive_references_explain_storage_and_keep_shared_files_out_of_cleanup(): void
  {
    $expired = $this->archive('uploaded');
    $expired->update(['retention_mode' => 'expires', 'expires_at' => now()->subDay()]);
    $running = $expired->replicate();
    $running->status = 'running';
    $running->save();
    $preview = app(SystemBackupCleanup::class)->preview();
    $summary = $preview->overview['reason_summary'];
    $groups = collect($summary['groups'])->keyBy('reason');
    $this->assertSame([], $preview->candidateIds);
    $this->assertSame(1, $summary['file_count']);
    $this->assertGreaterThan(0, $groups['running']['bytes']);
    $this->assertSame(0, $groups['shared_archive']['bytes']);
    $this->assertSame([], app(SystemBackupCleanup::class)->run(force: true)->deletedIds);
    Storage::disk('backups')->assertExists($expired->archive_path);
  }

  #[Test]
  public function partial_storage_scans_do_not_claim_complete_reason_totals_or_zero_other_files(): void
  {
    $backup = $this->archive('uploaded');
    symlink(Storage::disk('backups')->path($backup->archive_path), Storage::disk('backups')->path('linked.zip'));
    $plan = app(BackupRetentionPlan::class)->overview();
    $summary = $plan['reason_summary'];
    $this->assertSame('partial', $summary['status']);
    $this->assertNull($summary['bytes']);
    $this->assertNull($summary['file_count']);
    $this->assertSame(['file_count' => null, 'bytes' => null], $summary['other_files']);
    $this->assertGreaterThan(0, $summary['groups'][0]['bytes']);
    $html = view('webblocks-cms::admin.system.backups.retention-summary', ['plan' => $plan])->render();
    $this->assertStringContainsString(__('webblocks-cms::backup-retention.summary_partial'), $html);
    $this->assertStringContainsString('<td>—</td><td>—</td>', $html);
  }

  #[Test]
  public function reason_summary_handles_empty_storage_and_missing_roots_without_creating_them(): void
  {
    $plan = app(BackupRetentionPlan::class)->overview();
    $this->assertSame([], $plan['reason_summary']['groups']);
    $this->assertSame(0, $plan['reason_summary']['bytes']);
    $this->assertStringContainsString(__('webblocks-cms::backup-retention.summary_empty'), view('webblocks-cms::admin.system.backups.retention-summary', ['plan' => $plan])->render());
    $backup = $this->archive('uploaded');
    $root = Storage::disk('backups')->path('nonexistent-root');
    config()->set('filesystems.disks.backups.root', $root);
    Storage::forgetDisk('backups');
    $summary = app(BackupRetentionPlan::class)->overview()['reason_summary'];
    $this->assertSame('missing', $summary['status']);
    $this->assertSame([['reason' => 'unavailable', 'record_count' => 1, 'file_count' => 0, 'bytes' => 0]], $summary['groups']);
    $this->assertSame(0, $summary['bytes']);
    $this->assertDirectoryDoesNotExist($root);
    $this->assertDatabaseHas('wbcms_system_backups', ['id' => $backup->id]);
  }

  #[Test]
  public function files_changed_between_archive_inspection_and_disk_scan_make_totals_unconfirmed(): void
  {
    $backup = $this->archive('pre_update');
    $inspector = app(BackupRestoreArchiveInspector::class);
    $changingInspector = \Mockery::mock(BackupRestoreArchiveInspector::class);
    $changingInspector->shouldReceive('inspect')->once()->andReturnUsing(function ($path) use ($inspector, $backup) {
      $result = $inspector->inspect($path);
      Storage::disk('backups')->delete($backup->archive_path);

      return $result;
    });
    app()->instance(BackupRestoreArchiveInspector::class, $changingInspector);
    $plan = app(BackupRetentionPlan::class)->overview();
    $summary = $plan['reason_summary'];
    $this->assertSame('partial', $summary['status']);
    $this->assertNull($summary['bytes']);
    $this->assertNull($summary['file_count']);
    $this->assertSame(['file_count' => null, 'bytes' => null], $summary['other_files']);
    $this->assertGreaterThan(0, $summary['groups'][0]['bytes']);
    $this->assertSame(0, $plan['storage']['total_bytes']);
  }

  #[Test]
  public function new_api_routes_keep_read_and_write_capabilities_separate(): void
  {
    foreach (['show' => 'backups.read', 'update' => 'backups.settings.write'] as $action => $capability) {
      $route = Route::getRoutes()->getByName('internal-content-api.system.backups.retention.'.$action);
      $this->assertContains('internal-api.capability:'.$capability, $route->gatherMiddleware());
    }
    $request = new SystemBackupRetentionRequest;
    $request->setUserResolver(fn () => null);
    $this->assertFalse($request->authorize());
    $user = \Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('can')->with('manageRetention', SystemBackup::class)->andReturn(false);
    $request->setUserResolver(fn () => $user);
    $this->assertFalse($request->authorize());
  }
}
