<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Support\System\SystemBackupCleanup;
use WebBlocks\Cms\Support\System\SystemBackupManager;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Tests\TestCase;

class SystemBackupCleanupTest extends TestCase
{
  use RefreshDatabase;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function it_preserves_manual_uploaded_running_and_latest_pre_update_backups(): void
  {
    Storage::fake(SystemBackupManager::ARCHIVE_DISK);
    $this->app->make(SystemSettings::class)->save([
      SystemSettings::BACKUP_CLEANUP_PRE_UPDATE_DAYS => 14,
      SystemSettings::BACKUP_CLEANUP_KEEP_LATEST_PRE_UPDATE => 2,
      SystemSettings::BACKUP_CLEANUP_RESTORE_SAFETY_DAYS => 30,
      SystemSettings::BACKUP_CLEANUP_CONTENT_APPLY_DAYS => 7,
    ]);

    $oldPreUpdates = collect(range(1, 4))->map(fn (int $index) => $this->backup(SystemBackup::TYPE_PRE_UPDATE, now()->subDays(40 + $index)));
    $manual = $this->backup(SystemBackup::TYPE_MANUAL, now()->subYear());
    $uploaded = $this->backup(SystemBackup::TYPE_UPLOADED, now()->subYear());
    $running = $this->backup(SystemBackup::TYPE_CONTENT_APPLY, now()->subYear(), SystemBackup::STATUS_RUNNING);

    $preview = $this->app->make(SystemBackupCleanup::class)->preview();

    $this->assertCount(2, $preview->candidateIds);
    $this->assertEqualsCanonicalizing($oldPreUpdates->sortByDesc('created_at')->slice(2)->pluck('id')->all(), $preview->candidateIds);
    $this->assertNotContains($manual->id, $preview->candidateIds);
    $this->assertNotContains($uploaded->id, $preview->candidateIds);
    $this->assertNotContains($running->id, $preview->candidateIds);
  }

  #[Test]
  public function it_deletes_eligible_archive_and_record_together(): void
  {
    Storage::fake(SystemBackupManager::ARCHIVE_DISK);
    $backup = $this->backup(SystemBackup::TYPE_CONTENT_APPLY, now()->subDays(8));
    $backup->forceFill(['archive_path' => 'old.zip', 'archive_filename' => 'old.zip'])->save();
    Storage::disk(SystemBackupManager::ARCHIVE_DISK)->put('old.zip', 'archive');

    $result = $this->app->make(SystemBackupCleanup::class)->run(force: true);

    $this->assertSame([$backup->id], $result->deletedIds);
    $this->assertDatabaseMissing('wbcms_system_backups', ['id' => $backup->id]);
    Storage::disk(SystemBackupManager::ARCHIVE_DISK)->assertMissing('old.zip');
  }

  private function backup(string $type, $createdAt, string $status = SystemBackup::STATUS_COMPLETED): SystemBackup
  {
    $backup = SystemBackup::query()->create([
      'type' => $type,
      'status' => $status,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => SystemBackupManager::ARCHIVE_DISK,
      'archive_size_bytes' => 1024,
      'started_at' => $createdAt,
      'finished_at' => $status === SystemBackup::STATUS_RUNNING ? null : $createdAt,
    ]);
    $backup->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

    return $backup;
  }
}
