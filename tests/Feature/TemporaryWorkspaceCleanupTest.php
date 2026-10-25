<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\System\BackupOperations;
use WebBlocks\Cms\Support\System\MaintenanceCleanup;
use WebBlocks\Cms\Support\System\StorageUsageReport;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\Updates\Client\Updates\RunHeartbeat;
use WebBlocks\Cms\Tests\TestCase;

class TemporaryWorkspaceCleanupTest extends TestCase
{
  private string $root;

  private const UPDATE = 'app/system-updates/20260101-120000-12345678-1234-1234-1234-123456789abc';

  private const BACKUP = 'app/temp/system-backups/20260101120000-abcdefgh';

  private const RESTORE = 'app/temp/system-backup-restores/20260101120000-abcdefgh';

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->root = sys_get_temp_dir().'/wb-temporary-'.bin2hex(random_bytes(8));
    $this->app->useStoragePath($this->root.'/storage');
    foreach (['backups', 'public'] as $disk) {
      config()->set('filesystems.disks.'.$disk, ['driver' => 'local', 'root' => $this->root.'/'.$disk, 'throw' => true]);
      Storage::forgetDisk($disk);
    }
    config()->set('publisher-client.lock.name', 'system-updates:run');
  }

  protected function tearDown(): void
  {
    (new Filesystem)->deleteDirectory($this->root);
    parent::tearDown();
  }

  private function workspace(string $relative, string $contents = 'obsolete', int $hours = 48): string
  {
    $path = storage_path($relative);
    File::ensureDirectoryExists($path);
    File::put($path.'/fixture.sql', $contents);
    $old = now()->subHours($hours)->getTimestamp();
    touch($path.'/fixture.sql', $old);
    touch($path, $old);

    return $path;
  }

  #[Test]
  public function abandoned_update_history_does_not_block_obsolete_workspaces_forever(): void
  {
    $path = $this->workspace(self::UPDATE);
    $run = SystemUpdateRun::create(['from_version' => '1.0.0', 'to_version' => '1.0.1', 'status' => 'running', 'started_at' => now()->subDays(10)]);
    $cleanup = app(MaintenanceCleanup::class);
    $this->assertSame(1, $cleanup->previewTemporaryWorkspaces()->candidateCount);
    $result = $cleanup->run('temporary-workspaces');
    $this->assertSame(1, $result->deletedCount);
    $this->assertSame(8, $result->deletedBytes);
    $this->assertDirectoryDoesNotExist($path);
    $this->assertSame('running', $run->fresh()->status);
  }

  #[Test]
  public function expired_backup_and_restore_scratch_is_eligible_but_identity_recent_and_unknown_files_remain(): void
  {
    $backup = $this->workspace(self::BACKUP);
    $restore = $this->workspace(self::RESTORE);
    $recent = $this->workspace('app/temp/system-backups/20260102120000-abcdefgh', hours: 1);
    $unknown = $this->workspace('app/temp/unrecognized-old-work');
    $foreign = $this->workspace('app/temp/system-backups/not-a-run');
    File::ensureDirectoryExists(storage_path('app/system-updates'));
    File::put(storage_path('app/system-updates/installation-id'), 'keep');
    $this->assertSame(2, app(MaintenanceCleanup::class)->previewTemporaryWorkspaces()->candidateCount);
    $result = app(MaintenanceCleanup::class)->run('temporary-workspaces');
    $this->assertSame(2, $result->deletedCount);
    $this->assertSame(16, $result->deletedBytes);
    $this->assertDirectoryDoesNotExist($backup);
    $this->assertDirectoryDoesNotExist($restore);
    foreach ([$recent, $unknown, $foreign] as $path) {
      $this->assertDirectoryExists($path);
    }
    $this->assertSame('keep', File::get(storage_path('app/system-updates/installation-id')));
    $this->assertSame(4, app(StorageUsageReport::class)->overview()['categories']['temporary_workspaces']['files']);
  }

  #[Test]
  public function real_update_leases_are_never_taken_over_even_when_their_heartbeat_is_stale(): void
  {
    $path = $this->workspace(self::UPDATE);
    $lease = Cache::lock('system-updates:run', 600);
    $this->assertTrue($lease->get());
    Cache::put('system-updates:run:heartbeat', time() - 3600, 600);
    try {
      $this->assertSame(0, app(MaintenanceCleanup::class)->previewTemporaryWorkspaces()->candidateCount);
      $this->assertSame(0, app(MaintenanceCleanup::class)->run('temporary-workspaces')->deletedCount);
      $this->assertDirectoryExists($path);
      $this->assertTrue($lease->isOwnedByCurrentProcess());
    } finally {
      $lease->release();
    }
    $this->assertSame(1, app(MaintenanceCleanup::class)->run('temporary-workspaces')->deletedCount);
    $this->assertNull(Cache::get('system-updates:run:heartbeat'));
  }

  #[Test]
  public function fresh_heartbeat_and_recent_legacy_admission_protect_work_without_a_lock(): void
  {
    $path = $this->workspace(self::UPDATE);
    Cache::put('system-updates:run:heartbeat', time(), 600);
    $this->assertSame(0, app(MaintenanceCleanup::class)->run('temporary-workspaces')->deletedCount);
    Cache::forget('system-updates:run:heartbeat');
    foreach (['running', 'pending'] as $status) {
      $run = SystemUpdateRun::create(['from_version' => '1.0.0', 'to_version' => '1.0.1', 'status' => $status, 'started_at' => now()]);
      $this->assertSame(0, app(MaintenanceCleanup::class)->previewTemporaryWorkspaces()->candidateCount);
      $this->assertSame(0, app(MaintenanceCleanup::class)->run('temporary-workspaces')->deletedCount);
      $run->delete();
    }
    $this->assertDirectoryExists($path);
  }

  #[Test]
  public function queued_operations_and_fresh_operation_heartbeats_are_protected(): void
  {
    $path = $this->workspace(self::UPDATE);
    $id = (string) Str::uuid();
    DB::table('wbcms_admin_update_operations')->insert([
      'id' => $id, 'user_id' => 1, 'from_version' => '1.0.0', 'target_version' => '1.0.1',
      'checksum_sha256' => str_repeat('a', 64), 'status' => 'queued', 'started_at' => now()->subDays(2),
    ]);
    $this->assertSame(0, app(MaintenanceCleanup::class)->run('temporary-workspaces')->deletedCount);
    DB::table('wbcms_admin_update_operations')->where('id', $id)->update(['status' => 'running', 'heartbeat_at' => now()]);
    $this->assertSame(0, app(MaintenanceCleanup::class)->run('temporary-workspaces')->deletedCount);
    DB::table('wbcms_admin_update_operations')->where('id', $id)->update(['heartbeat_at' => now()->subDays(2)]);
    $this->assertSame(1, app(MaintenanceCleanup::class)->run('temporary-workspaces')->deletedCount);
    $this->assertDirectoryDoesNotExist($path);
    $this->assertDatabaseHas('wbcms_admin_update_operations', ['id' => $id, 'status' => 'running']);
  }

  #[Test]
  public function completed_backup_metadata_cannot_hide_a_process_held_backup_or_restore_lease(): void
  {
    $path = $this->workspace(self::BACKUP);
    $operations = app(BackupOperations::class);
    $operations->run(function () use ($path): void {
      $this->assertSame(0, app(MaintenanceCleanup::class)->previewTemporaryWorkspaces()->candidateCount);
      $this->assertSame(0, app(MaintenanceCleanup::class)->run('temporary-workspaces')->deletedCount);
      $this->assertDirectoryExists($path);
    });
    $this->assertSame(1, app(MaintenanceCleanup::class)->run('temporary-workspaces')->deletedCount);
  }

  #[Test]
  public function scratch_deletion_holds_live_update_and_process_backup_leases_and_releases_them_afterwards(): void
  {
    $path = $this->workspace(self::RESTORE);
    File::partialMock()->shouldReceive('deleteDirectory')->with($path)->once()->andReturnUsing(function (string $directory): bool {
      $other = Cache::lock('system-updates:run', 60);
      $this->assertFalse($other->get());
      $this->assertFalse(app(RunHeartbeat::class)->isStale());
      $handle = fopen(config('filesystems.disks.backups.root').'/'.BackupOperations::LOCK_FILE, 'r');
      try {
        $this->assertFalse(flock($handle, LOCK_EX | LOCK_NB));
      } finally {
        fclose($handle);
      }

      return (new Filesystem)->deleteDirectory($directory);
    });
    $this->assertSame(1, app(MaintenanceCleanup::class)->run('temporary-workspaces')->deletedCount);
    $this->assertNull(Cache::get('system-updates:run:heartbeat'));
    $lease = Cache::lock('system-updates:run', 60);
    $this->assertTrue($lease->get());
    $lease->release();
    $this->assertFalse(app(BackupOperations::class)->isBusy());
  }

  #[Test]
  public function a_recent_nested_write_invalidates_an_earlier_preview(): void
  {
    $path = $this->workspace(self::RESTORE);
    $this->assertSame(1, app(MaintenanceCleanup::class)->previewTemporaryWorkspaces()->candidateCount);
    File::ensureDirectoryExists($path.'/nested');
    File::put($path.'/nested/live.sql', 'new work');
    touch($path, now()->subDays(2)->getTimestamp());
    $this->assertSame(0, app(MaintenanceCleanup::class)->run('temporary-workspaces')->deletedCount);
    $this->assertFileExists($path.'/nested/live.sql');
  }

  #[Test]
  public function symlinked_workspaces_ancestors_and_nested_files_are_never_followed(): void
  {
    $outside = $this->workspace('app/outside');
    $nested = $this->workspace(self::BACKUP);
    symlink($outside.'/fixture.sql', $nested.'/external.sql');
    touch($nested, now()->subDays(2)->getTimestamp());
    File::ensureDirectoryExists(dirname(storage_path(self::UPDATE)));
    symlink($outside, storage_path(self::UPDATE));
    File::ensureDirectoryExists(storage_path('app/temp'));
    symlink($outside, storage_path('app/temp/system-backup-restores'));
    $this->assertSame(0, app(MaintenanceCleanup::class)->run('temporary-workspaces')->deletedCount);
    $this->assertFileExists($outside.'/fixture.sql');
    $this->assertFileExists($nested.'/fixture.sql');
  }

  #[Test]
  public function overlapping_backup_storage_and_unknown_updater_backup_directories_are_retained(): void
  {
    $path = $this->workspace(self::UPDATE);
    $unknown = $this->workspace('app/system-updates/backups');
    config()->set('filesystems.disks.backups.root', storage_path('app/system-updates'));
    Storage::forgetDisk('backups');
    $this->assertSame(0, app(MaintenanceCleanup::class)->run('temporary-workspaces')->deletedCount);
    $this->assertDirectoryExists($path);
    $this->assertDirectoryExists($unknown);
  }

  #[Test]
  public function previews_create_neither_a_backup_root_nor_a_backup_lock_file(): void
  {
    $this->workspace(self::BACKUP);
    $this->assertSame(1, app(MaintenanceCleanup::class)->previewTemporaryWorkspaces()->candidateCount);
    $this->assertDirectoryDoesNotExist(config('filesystems.disks.backups.root'));
    $this->assertSame(0, DB::table('wbcms_maintenance_cleanup_states')->count());
  }

  #[Test]
  public function scheduled_scratch_cleanup_requires_its_explicit_flag_and_records_the_actual_result(): void
  {
    $path = $this->workspace(self::BACKUP);
    $this->artisan('system:cleanup', ['category' => 'temporary-workspaces', '--scheduled' => true])->assertSuccessful();
    $this->assertDirectoryExists($path);
    $this->assertDatabaseCount('wbcms_maintenance_cleanup_states', 0);
    app(SystemSettings::class)->save([SystemSettings::CLEANUP_AUTOMATIC_KEYS['temporary-workspaces'] => true]);
    $this->artisan('system:cleanup', ['category' => 'temporary-workspaces', '--scheduled' => true])->assertSuccessful();
    $this->assertDirectoryDoesNotExist($path);
    $this->assertDatabaseHas('wbcms_maintenance_cleanup_states', [
      'category' => 'temporary-workspaces', 'source' => 'scheduled', 'status' => 'completed',
      'deleted_count' => 1, 'deleted_bytes' => 8, 'failure_count' => 0,
    ]);
    $this->assertFalse(app(SystemSettings::class)->automaticMaintenanceSettings()['media-variants']);
  }

  #[Test]
  public function a_process_file_lease_blocks_cleanup_even_without_any_running_database_record(): void
  {
    $path = $this->workspace(self::RESTORE);
    $root = config('filesystems.disks.backups.root');
    File::ensureDirectoryExists($root);
    $handle = fopen($root.'/'.BackupOperations::LOCK_FILE, 'c');
    flock($handle, LOCK_EX | LOCK_NB);
    try {
      $this->assertTrue(app(BackupOperations::class)->isBusy());
      $this->assertSame(0, app(MaintenanceCleanup::class)->run('temporary-workspaces')->deletedCount);
      $this->assertDirectoryExists($path);
    } finally {
      fclose($handle);
    }
    $this->assertFalse(app(BackupOperations::class)->isBusy());
    $this->assertSame(1, app(MaintenanceCleanup::class)->run('temporary-workspaces')->deletedCount);
  }

  #[Test]
  public function deletion_failure_releases_both_leases_and_does_not_leave_a_live_heartbeat(): void
  {
    $path = $this->workspace(self::BACKUP);
    File::partialMock()->shouldReceive('deleteDirectory')->with($path)->once()->andThrow(new \RuntimeException('Synthetic filesystem failure.'));
    $result = app(MaintenanceCleanup::class)->run('temporary-workspaces');
    $this->assertNotEmpty($result->failures);
    $this->assertDirectoryExists($path);
    $this->assertNull(Cache::get('system-updates:run:heartbeat'));
    $lease = Cache::lock('system-updates:run', 60);
    $this->assertTrue($lease->get());
    $lease->release();
    $this->assertFalse(app(BackupOperations::class)->isBusy());
    $this->assertDatabaseHas('wbcms_maintenance_cleanup_states', ['category' => 'temporary-workspaces', 'status' => 'failed']);
  }

  #[Test]
  public function later_failure_preserves_the_count_and_bytes_of_already_removed_workspaces(): void
  {
    $first = $this->workspace(self::UPDATE);
    $second = $this->workspace(self::BACKUP);
    File::partialMock()->shouldReceive('deleteDirectory')->with($first)->once()->andReturnUsing(fn ($path) => (new Filesystem)->deleteDirectory($path));
    File::shouldReceive('deleteDirectory')->with($second)->once()->andThrow(new \RuntimeException('Synthetic filesystem failure.'));
    $result = app(MaintenanceCleanup::class)->run('temporary-workspaces');
    $this->assertSame(1, $result->deletedCount);
    $this->assertSame(8, $result->deletedBytes);
    $this->assertCount(1, $result->failures);
    $this->assertDirectoryDoesNotExist($first);
    $this->assertDirectoryExists($second);
    $this->assertDatabaseHas('wbcms_maintenance_cleanup_states', ['category' => 'temporary-workspaces', 'status' => 'failed', 'deleted_count' => 1, 'deleted_bytes' => 8, 'failure_count' => 1]);
  }

  #[Test]
  public function unverified_heartbeat_writes_never_authorize_deletion(): void
  {
    $path = $this->workspace(self::BACKUP);
    $original = Cache::getFacadeRoot();
    foreach (['drop', 'unreadable'] as $mode) {
      $store = new class($mode) extends ArrayStore
      {
        public function __construct(private string $mode) {}

        public function put($key, $value, $seconds)
        {
          return $key === 'system-updates:run:heartbeat' && $this->mode === 'drop' ? false : parent::put($key, $value, $seconds);
        }

        public function get($key)
        {
          return $key === 'system-updates:run:heartbeat' && $this->mode === 'unreadable' ? null : parent::get($key);
        }
      };
      Cache::swap(new Repository($store));
      try {
        $result = app(MaintenanceCleanup::class)->run('temporary-workspaces');
        $this->assertSame(0, $result->deletedCount);
        $this->assertNotEmpty($result->failures);
        $this->assertDirectoryExists($path);
        $lease = Cache::lock('system-updates:run', 60);
        $this->assertTrue($lease->get());
        $lease->release();
      } finally {
        Cache::swap($original);
      }
    }
  }

  #[Test]
  public function numeric_string_heartbeats_from_redis_style_stores_remain_compatible(): void
  {
    $this->workspace(self::BACKUP);
    $original = Cache::getFacadeRoot();
    $store = new class extends ArrayStore
    {
      public function get($key)
      {
        $value = parent::get($key);

        return $key === 'system-updates:run:heartbeat' && is_int($value) ? (string) $value : $value;
      }
    };
    Cache::swap(new Repository($store));
    try {
      $result = app(MaintenanceCleanup::class)->run('temporary-workspaces');
      $this->assertSame(1, $result->deletedCount);
      $this->assertSame([], $result->failures);
    } finally {
      Cache::swap($original);
    }
  }
}
