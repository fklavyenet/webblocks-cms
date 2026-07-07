<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Media as Asset;
use WebBlocks\Cms\Models\MediaFolder as AssetFolder;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Models\SystemBackupRestore;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteTransferPackage;
use WebBlocks\Cms\Support\System\BackupRestoreInspection;
use WebBlocks\Cms\Support\System\BackupRestoreResult;
use WebBlocks\Cms\Support\System\DatabaseDumpWriter;
use WebBlocks\Cms\Support\System\SystemBackupArchivePackage;
use WebBlocks\Cms\Support\System\SystemBackupArchiveResolution;
use WebBlocks\Cms\Support\System\SystemBackupArchiveResolver;
use WebBlocks\Cms\Support\System\SystemBackupManager;
use WebBlocks\Cms\Support\System\SystemBackupRestoreManager;
use ZipArchive;

class SystemBackupsTest extends TestCase
{
  use RefreshDatabase;

  private array $temporaryDirectories = [];

  #[Test]
  public function admin_can_view_backups_page(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.backups.index'));

    $response->assertOk();
    $response->assertSee('Backups');
    $response->assertSee('Create backup');
    $response->assertSee('Upload backup');
    $response->assertDontSee('>Cancel<', false);
    $response->assertSee('No backup history yet');
  }

  #[Test]
  public function backup_screens_use_authenticated_admin_locale_for_card_and_body_copy(): void
  {
    $user = User::factory()->superAdmin()->create([
      'admin_locale' => 'tr',
    ]);

    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'manual.zip',
      'archive_filename' => 'manual.zip',
      'archive_size_bytes' => 1024,
      'started_at' => now()->subMinutes(3),
      'finished_at' => now()->subMinutes(2),
      'duration_ms' => 1000,
      'summary' => null,
      'triggered_by_user_id' => $user->id,
    ]);

    $indexResponse = $this->actingAs($user)->get(route('admin.system.backups.index'));

    $indexResponse->assertOk();
    $indexResponse->assertSee('<html lang="tr">', false);
    $indexResponse->assertSee('Son Yedek Durumu');
    $indexResponse->assertSee('Yedek Onerisi');
    $indexResponse->assertSee('Yedek oluştur');
    $indexResponse->assertDontSeeText('Latest Backup Status');
    $indexResponse->assertDontSeeText('Backup Recommendation');
    $indexResponse->assertDontSeeText('Create backup');

    $uploadResponse = $this->actingAs($user)->get(route('admin.system.backups.upload'));

    $uploadResponse->assertOk();
    $uploadResponse->assertSee('Yedek yukle');
    $uploadResponse->assertSee('Yalnizca tam sistem restore');
    $uploadResponse->assertDontSeeText('Upload Backup');
    $uploadResponse->assertDontSeeText('Full system restore only');

    $detailResponse = $this->actingAs($user)->get(route('admin.system.backups.show', $backup));

    $detailResponse->assertOk();
    $detailResponse->assertSee('Calistirma durumu');
    $detailResponse->assertSee('Arsiv Metadata');
    $detailResponse->assertSee('Tehlikeli Bolge');
    $detailResponse->assertDontSeeText('Run Status');
    $detailResponse->assertDontSeeText('Archive Metadata');
    $detailResponse->assertDontSeeText('Danger Zone');
  }

  #[Test]
  public function backups_index_has_exactly_one_upload_backup_action_and_no_duplicate_system_updates_control(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.backups.index'));
    $content = $response->getContent();

    $response->assertOk();
    $response->assertSee('Upload backup');
    $response->assertSee(route('admin.system.backups.upload'), false);
    $this->assertStringContainsString('<strong>Backups</strong>', $content);
    $this->assertStringContainsString(route('admin.system.backups.upload'), $content);
    $this->assertStringNotContainsString('<div class="wb-page-actions">', $content);
    $this->assertSame(1, substr_count($content, 'Upload backup'));
  }

  #[Test]
  public function backups_index_renders_summary_filters_and_listing_in_admin_standard_order(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.backups.index'));
    $content = $response->getContent();

    $response->assertOk();
    $this->assertStringOrder($content, 'Latest Backup Status', 'Backup Recommendation');
    $this->assertStringOrder($content, 'Backup Recommendation', 'id="backups_search"');
    $this->assertStringOrder($content, 'id="backups_search"', '<strong>Backups</strong>');
    $this->assertStringOrder($content, 'id="backups_search"', 'No backup history yet');
  }

  #[Test]
  public function recommendation_card_keeps_create_backup_action_in_listing_header(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.backups.index'));

    $response->assertOk();
    $response->assertSee('Backup Recommendation');
    $this->assertSame(1, substr_count($response->getContent(), 'Create backup'));
    $this->assertSame(1, substr_count($response->getContent(), 'Upload backup'));
    $response->assertDontSee('>Cancel<', false);
  }

  #[Test]
  public function recommendation_card_hides_duplicate_create_action_when_a_recent_successful_backup_exists(): void
  {
    $user = User::factory()->superAdmin()->create();

    SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'recent.zip',
      'archive_filename' => 'recent.zip',
      'started_at' => now()->subMinutes(5),
      'finished_at' => now()->subMinutes(4),
      'summary' => 'Completed.',
    ]);

    $response = $this->actingAs($user)->get(route('admin.system.backups.index'));

    $response->assertOk();
    $response->assertSee('Recent backup available');
    $this->assertSame(1, substr_count($response->getContent(), 'Create backup'));
    $this->assertSame(1, substr_count($response->getContent(), 'Upload backup'));
  }

  #[Test]
  public function backups_row_actions_use_table_action_cell_and_non_wrapping_group(): void
  {
    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'actions.zip',
      'archive_filename' => 'actions.zip',
      'started_at' => now()->subMinutes(5),
      'finished_at' => now()->subMinutes(4),
      'summary' => 'Completed.',
    ]);

    $response = $this->actingAs($user)->get(route('admin.system.backups.index'));
    $content = $response->getContent();

    $response->assertOk();
    $this->assertStringOrder($content, 'id="backups_search"', '<table class="wb-table wb-table-striped wb-table-hover">');
    $response->assertSee('<th>Actions</th>', false);
    $response->assertSee('<td class="wb-table-actions">', false);
    $response->assertSee('<div class="wb-action-group">', false);
    $this->assertStringContainsString(
      '<td class="wb-table-actions">'."\n".'                                            <div class="wb-action-group">',
      $content
    );
    $this->assertStringNotContainsString('class="wb-action-group wb-stack', $content);
    $this->assertStringNotContainsString('class="wb-action-group wb-flex-col', $content);
    $this->assertStringNotContainsString('class="wb-action-group wb-whitespace-nowrap"', $content);
    $this->assertStringContainsString(route('admin.system.backups.show', $backup), $content);
    $this->assertStringContainsString('data-wb-target="#delete-backup-'.$backup->id.'-modal"', $content);
  }

  #[Test]
  public function table_action_css_scopes_nowrap_to_table_action_cells(): void
  {
    $css = File::get(public_path('cms/css/admin.css'));

    $this->assertMatchesRegularExpression('/\\.wb-action-group\\s*\\{[^}]*flex-wrap:\\s*wrap;/s', $css);
    $this->assertDoesNotMatchRegularExpression('/\\.wb-action-group\\s*\\{[^}]*white-space:\\s*nowrap;/s', $css);
    $this->assertMatchesRegularExpression('/\\.wb-table-actions\\s*\\{[^}]*text-align:\\s*left;[^}]*white-space:\\s*nowrap;/s', $css);
    $this->assertMatchesRegularExpression('/\\.wb-table-actions \\.wb-action-group\\s*\\{[^}]*flex-wrap:\\s*nowrap;/s', $css);
  }

  #[Test]
  public function admin_can_view_backup_upload_screen(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.backups.upload'));

    $response->assertOk();
    $response->assertSee('Upload Backup');
    $response->assertSee('This is not a site export/import package.');
  }

  #[Test]
  public function backups_page_still_loads_when_backup_table_is_missing(): void
  {
    Schema::drop('wbcms_system_backups');

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.backups.index'));

    $response->assertOk();
    $response->assertSee('Backups');
    $response->assertSee('Backup storage is not ready yet');
    $response->assertSee('wbcms_system_backups');
  }

  #[Test]
  public function admin_can_create_backup_record_and_download_artifact(): void
  {
    $backupsRoot = $this->useRealBackupsDiskRoot('create-backup-artifact');
    $exportsRoot = $this->useRealExportsDiskRoot('create-backup-export-root');
    $publicRoot = $this->makeTemporaryDirectory('create-backup-public-root');
    config()->set('filesystems.disks.public.root', $publicRoot);

    $user = User::factory()->superAdmin()->create();
    $folder = AssetFolder::query()->create(['name' => 'Docs', 'slug' => 'docs']);

    File::ensureDirectoryExists($publicRoot.'/media/documents');
    File::put($publicRoot.'/media/documents/readme.txt', 'backup me');

    Asset::query()->create([
      'folder_id' => $folder->id,
      'disk' => 'public',
      'path' => 'media/documents/readme.txt',
      'filename' => 'readme.txt',
      'original_name' => 'readme.txt',
      'extension' => 'txt',
      'mime_type' => 'text/plain',
      'size' => 9,
      'kind' => Asset::KIND_DOCUMENT,
      'visibility' => 'public',
      'title' => 'Readme',
      'uploaded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('admin.system.backups.store'));

    $response->assertStatus(302);
    $this->assertSame(route('admin.system.backups.index'), $response->headers->get('Location'));

    $backup = SystemBackup::query()->latest()->first();

    $this->assertNotNull($backup);
    $this->assertSame(SystemBackup::STATUS_COMPLETED, $backup->status);
    $this->assertSame(SystemBackup::TYPE_MANUAL, $backup->type);
    $this->assertSame(SystemBackupManager::ARCHIVE_DISK, $backup->archive_disk);
    $this->assertNotNull($backup->archive_path);

    $this->assertStringNotContainsString('/', (string) $backup->archive_path);
    $this->assertFileExists($backupsRoot.'/'.$backup->archive_path);
    $this->assertFileDoesNotExist($exportsRoot.'/'.$backup->archive_path);
    $this->assertFileDoesNotExist($exportsRoot.'/uploaded/'.$backup->archive_path);

    $archivePath = $backupsRoot.'/'.$backup->archive_path;
    $archive = new ZipArchive;

    $this->assertTrue($archive->open($archivePath) === true);
    $this->assertNotFalse($archive->locateName('database/database.sql'));
    $this->assertNotFalse($archive->locateName('uploads/public/media/documents/readme.txt'));
    $this->assertNotFalse($archive->locateName('manifest.json'));
    $this->assertStringContainsString('WebBlocks CMS', (string) $archive->getFromName('manifest.json'));
    $archive->close();

    $download = $this->actingAs($user)->get(route('admin.system.backups.download', $backup));

    $download->assertOk();
    $download->assertDownload($backup->archive_filename);
  }

  #[Test]
  public function created_backup_archive_contains_its_own_backup_record_as_completed_instead_of_running(): void
  {
    $backupsRoot = $this->useRealBackupsDiskRoot('create-backup-sql-state');
    $publicRoot = $this->makeTemporaryDirectory('create-backup-sql-public-root');
    config()->set('filesystems.disks.public.root', $publicRoot);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->post(route('admin.system.backups.store'));

    $response->assertStatus(302);
    $this->assertSame(route('admin.system.backups.index'), $response->headers->get('Location'));

    $backup = SystemBackup::query()->latest()->first();

    $this->assertNotNull($backup);
    $this->assertSame(SystemBackup::STATUS_COMPLETED, $backup->status);

    $archive = new ZipArchive;
    $archivePath = $backupsRoot.'/'.(string) $backup->archive_path;
    $this->assertTrue($archive->open($archivePath) === true);

    $databaseSql = $archive->getFromName('database/database.sql');

    $archive->close();

    $this->assertIsString($databaseSql);

    $snapshotDatabasePath = $this->makeTemporaryDirectory('snapshot-db').'/snapshot.sqlite';
    $snapshotConnection = new \PDO('sqlite:'.$snapshotDatabasePath);
    $snapshotConnection->exec($databaseSql);

    $statement = $snapshotConnection->prepare('select status, finished_at, archive_path, archive_filename from wbcms_system_backups where id = :id');
    $statement->execute(['id' => $backup->id]);
    $dumpedBackup = $statement->fetch(\PDO::FETCH_ASSOC);

    $this->assertIsArray($dumpedBackup);
    $this->assertSame(SystemBackup::STATUS_COMPLETED, $dumpedBackup['status']);
    $this->assertNotEmpty($dumpedBackup['finished_at']);
    $this->assertSame($backup->archive_path, $dumpedBackup['archive_path']);
    $this->assertSame($backup->archive_filename, $dumpedBackup['archive_filename']);
  }

  #[Test]
  public function failed_backup_is_recorded_as_failed(): void
  {
    Storage::fake('public');
    Storage::fake('backups');

    $user = User::factory()->superAdmin()->create();
    $mock = Mockery::mock(DatabaseDumpWriter::class);
    $mock->shouldReceive('dumpTo')->once()->andThrow(new \RuntimeException('mysqldump is not available.'));
    $this->app->instance(DatabaseDumpWriter::class, $mock);

    $response = $this->actingAs($user)->post(route('admin.system.backups.store'));

    $response->assertStatus(302);
    $this->assertSame(route('admin.system.backups.index'), $response->headers->get('Location'));

    $backup = SystemBackup::query()->latest()->first();

    $this->assertNotNull($backup);
    $this->assertSame(SystemBackup::STATUS_FAILED, $backup->status);
    $this->assertSame('mysqldump is not available.', $backup->error_message);

    $followUp = $this->actingAs($user)->get(route('admin.system.backups.index'));
    $followUp->assertSee('Backup Failed');
    $followUp->assertSee('mysqldump is not available.');
  }

  #[Test]
  public function backup_creation_bootstraps_missing_backups_disk_root_for_fresh_consumer_like_installs(): void
  {
    config()->set('filesystems.disks.backups', null);

    $backupsRoot = storage_path('app/backups');
    File::deleteDirectory($backupsRoot);

    $publicRoot = $this->makeTemporaryDirectory('fresh-consumer-public-root');
    config()->set('filesystems.disks.public.root', $publicRoot);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->post(route('admin.system.backups.store'));

    $response->assertRedirect(route('admin.system.backups.index'));

    $backup = SystemBackup::query()->latest()->firstOrFail();

    $this->assertSame(SystemBackup::STATUS_COMPLETED, $backup->status);
    $this->assertDirectoryExists($backupsRoot);
    $this->assertFileExists($backupsRoot.'/'.$backup->archive_path);
  }

  #[Test]
  public function backup_disk_root_creation_is_idempotent_for_repeated_backups(): void
  {
    config()->set('filesystems.disks.backups', null);

    $backupsRoot = storage_path('app/backups');
    File::deleteDirectory($backupsRoot);

    $publicRoot = $this->makeTemporaryDirectory('idempotent-backup-public-root');
    config()->set('filesystems.disks.public.root', $publicRoot);

    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->post(route('admin.system.backups.store'))->assertRedirect(route('admin.system.backups.index'));
    $this->actingAs($user)->post(route('admin.system.backups.store'))->assertRedirect(route('admin.system.backups.index'));

    $this->assertSame(2, SystemBackup::query()->where('status', SystemBackup::STATUS_COMPLETED)->count());
    $this->assertDirectoryExists($backupsRoot);
  }

  #[Test]
  public function backup_failure_detail_is_sanitized_before_it_is_persisted(): void
  {
    Storage::fake('public');
    Storage::fake('backups');

    $user = User::factory()->superAdmin()->create();
    $mock = Mockery::mock(DatabaseDumpWriter::class);
    $mock->shouldReceive('dumpTo')->once()->andThrow(new \RuntimeException('Database dump failed with password=supersecret --defaults-extra-file=/tmp/mysql.cnf in '.storage_path('app/private')));
    $this->app->instance(DatabaseDumpWriter::class, $mock);

    $this->actingAs($user)->post(route('admin.system.backups.store'));

    $backup = SystemBackup::query()->latest()->firstOrFail();

    $this->assertSame(SystemBackup::STATUS_FAILED, $backup->status);
    $this->assertSame('Database dump failed with password=[redacted] --defaults-extra-file=[redacted] in [storage_path]/app/private', $backup->error_message);
    $this->assertStringContainsString('Backup failed: Database dump failed with password=[redacted] --defaults-extra-file=[redacted] in [storage_path]/app/private', (string) $backup->output);
    $this->assertStringNotContainsString('supersecret', (string) $backup->output);
    $this->assertStringNotContainsString('/tmp/mysql.cnf', (string) $backup->output);
    $this->assertStringNotContainsString(storage_path('app/private'), (string) $backup->output);
  }

  #[Test]
  public function backup_detail_page_shows_visible_restore_danger_zone_for_restorable_backups(): void
  {
    Storage::fake('backups');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'demo.zip',
      'archive_filename' => 'demo.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    $this->createBackupArchive($backup->archive_path, [
      'manifest.json' => json_encode($this->backupManifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      'database/database.sql' => 'select 1;',
      'uploads/public/media/example.txt' => 'restored',
    ]);

    $response = $this->actingAs($user)->get(route('admin.system.backups.show', $backup));

    $response->assertOk();
    $response->assertSee('Danger Zone');
    $response->assertSee('Restore backup');
    $response->assertSee('This restores a full system backup. It will overwrite the current database and uploaded files.');
    $response->assertSee('I understand this will overwrite current data.');
    $response->assertSee(route('admin.system.backups.restore', $backup), false);
    $response->assertSee('data-wb-restore-submit', false);
    $response->assertSee('disabled', false);
    $response->assertSee('required', false);
    $response->assertSee('Manifest Preview');
  }

  #[Test]
  public function backup_detail_page_renders_restore_history_delete_action(): void
  {
    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'demo.zip',
      'archive_filename' => 'demo.zip',
      'started_at' => now()->subMinutes(5),
      'finished_at' => now()->subMinutes(4),
      'summary' => 'Completed.',
    ]);
    $restore = SystemBackupRestore::query()->create([
      'source_backup_id' => $backup->id,
      'source_archive_disk' => 'backups',
      'source_archive_path' => $backup->archive_path,
      'source_archive_filename' => $backup->archive_filename,
      'status' => SystemBackupRestore::STATUS_FAILED,
      'restored_parts' => ['database'],
      'started_at' => now()->subMinutes(3),
      'finished_at' => now()->subMinutes(2),
      'summary' => 'Restore failed.',
    ]);

    $response = $this->actingAs($user)->get(route('admin.system.backups.show', $backup));

    $response->assertOk();
    $response->assertSee('Restore History');
    $response->assertSee('<th>Actions</th>', false);
    $response->assertSee('<div class="wb-action-group">', false);
    $response->assertDontSee('wb-backup-actions', false);
    $response->assertDontSee('wb-justify-end', false);
    $response->assertSee('action="'.route('admin.system.backups.restores.destroy', [$backup, $restore]).'"', false);
    $response->assertSee('name="_method" value="DELETE"', false);
    $response->assertSee('Delete this restore history entry? This will not delete any backup archive.');
  }

  #[Test]
  public function successful_restore_redirects_to_backups_index_with_success_flash_even_if_original_backup_record_disappears(): void
  {
    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'restore-source.zip',
      'archive_filename' => 'restore-source.zip',
      'started_at' => now()->subMinute(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    $mock = Mockery::mock(SystemBackupRestoreManager::class);
    $mock->shouldReceive('restoreFromBackup')
      ->once()
      ->withArgs(fn (SystemBackup $passedBackup, ?int $userId): bool => (int) $passedBackup->id === (int) $backup->id && $userId === $user->id)
      ->andReturnUsing(function () use ($backup): BackupRestoreResult {
        $backup->delete();

        return $this->makeRestoreResult($backup);
      });

    $this->app->instance(SystemBackupRestoreManager::class, $mock);

    $response = $this->actingAs($user)
      ->followingRedirects()
      ->post(route('admin.system.backups.restore', $backup), [
        'acknowledge_restore_risk' => '1',
      ]);

    $response->assertOk();
    $response->assertSee('Backups');
    $response->assertSee('System restore completed successfully.');
    $response->assertDontSee('Restore Failed');
    $this->assertDatabaseMissing('wbcms_system_backups', ['id' => $backup->id]);
  }

  #[Test]
  public function successful_restore_creates_a_completed_safety_backup_and_leaves_no_running_backup_records(): void
  {
    $user = User::factory()->superAdmin()->create();
    $sourceBackup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'restore-source.zip',
      'archive_filename' => 'restore-source.zip',
      'started_at' => now()->subMinute(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);
    $safetyBackup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_RESTORE_SAFETY,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => SystemBackupManager::ARCHIVE_DISK,
      'archive_path' => 'generated-safety.zip',
      'archive_filename' => 'generated-safety.zip',
      'archive_size_bytes' => 1234,
      'started_at' => now()->subSeconds(30),
      'finished_at' => now()->subSeconds(20),
      'summary' => 'Completed.',
      'output' => 'Safety backup completed.',
      'triggered_by_user_id' => $user->id,
    ]);
    $restoreRecord = SystemBackupRestore::query()->create([
      'source_backup_id' => $sourceBackup->id,
      'source_archive_disk' => 'backups',
      'source_archive_path' => $sourceBackup->archive_path,
      'source_archive_filename' => $sourceBackup->archive_filename,
      'safety_backup_id' => $safetyBackup->id,
      'status' => SystemBackupRestore::STATUS_COMPLETED,
      'restored_parts' => ['database', 'uploads'],
      'started_at' => now()->subSeconds(15),
      'finished_at' => now()->subSeconds(5),
      'summary' => 'Restore completed from '.$sourceBackup->archive_filename.'.',
    ]);

    $mock = Mockery::mock(SystemBackupRestoreManager::class);
    $mock->shouldReceive('restoreFromBackup')
      ->once()
      ->withArgs(fn (SystemBackup $passedBackup, ?int $userId): bool => (int) $passedBackup->id === (int) $sourceBackup->id && $userId === $user->id)
      ->andReturn(new BackupRestoreResult(
        sourceBackup: $sourceBackup,
        sourceArchivePath: (string) $sourceBackup->archive_path,
        sourceArchiveFilename: (string) $sourceBackup->archive_filename,
        inspection: new BackupRestoreInspection(
          manifest: $this->backupManifest(),
          includesDatabase: true,
          includesUploads: true,
          databaseSqlPath: 'database/database.sql',
          uploadsRootPath: 'uploads/public',
        ),
        safetyBackup: $safetyBackup,
        output: ['Restore completed.'],
        restoreRecord: $restoreRecord,
      ));
    $this->app->instance(SystemBackupRestoreManager::class, $mock);

    $response = $this->actingAs($user)
      ->post(route('admin.system.backups.restore', $sourceBackup->fresh()), [
        'acknowledge_restore_risk' => '1',
      ]);

    $response->assertStatus(302);
    $this->assertSame(route('admin.system.backups.index'), $response->headers->get('Location'));
    $this->assertSame(SystemBackup::STATUS_COMPLETED, $safetyBackup->status);
    $this->assertNotNull($safetyBackup->finished_at);
    $this->assertSame(SystemBackupManager::ARCHIVE_DISK, $safetyBackup->archive_disk);
    $this->assertNotNull($safetyBackup->archive_path);
    $this->assertNotNull($safetyBackup->archive_filename);
    $this->assertSame(SystemBackupRestore::STATUS_COMPLETED, $restoreRecord->status);
    $this->assertSame($safetyBackup->id, $restoreRecord->safety_backup_id);
    $this->assertSame('Restore completed from '.$sourceBackup->archive_filename.'.', $restoreRecord->summary);
    $this->assertSame(0, SystemBackup::query()->where('status', SystemBackup::STATUS_RUNNING)->count());
  }

  #[Test]
  public function backup_detail_route_still_works_for_existing_backup_records(): void
  {
    Storage::fake('backups');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'existing-detail.zip',
      'archive_filename' => 'existing-detail.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    $this->createBackupArchive($backup->archive_path, [
      'manifest.json' => json_encode($this->backupManifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      'database/database.sql' => 'select 1;',
    ]);

    $response = $this->actingAs($user)->get(route('admin.system.backups.show', $backup));

    $response->assertOk();
    $response->assertSee('Restore backup');
    $response->assertSee(route('admin.system.backups.restore', $backup), false);
    $response->assertSee('Source filename:');
  }

  #[Test]
  public function valid_backup_zip_upload_creates_backup_record_and_redirects_to_detail_page(): void
  {
    Storage::fake('backups');
    Storage::fake('site-exports');
    Storage::fake('site-transfers');

    $user = User::factory()->superAdmin()->create();
    $uploadedFile = $this->makeUploadedBackupArchive('downloaded-backup.zip', [
      'manifest.json' => json_encode(array_merge($this->backupManifest(), [
        'app_version' => '1.2.3',
        'created_at' => '2026-05-01T10:00:00+00:00',
      ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      'database/database.sql' => 'select 1;',
      'uploads/public/media/example.txt' => 'restored',
    ]);

    $response = $this->actingAs($user)->post(route('admin.system.backups.upload.store'), [
      'archive' => $uploadedFile,
    ]);

    $backup = SystemBackup::query()->latest()->first();

    $this->assertNotNull($backup);
    $this->assertSame(SystemBackup::TYPE_UPLOADED, $backup->type);
    $this->assertSame(SystemBackup::STATUS_COMPLETED, $backup->status);
    $this->assertSame('downloaded-backup.zip', $backup->label);
    $this->assertSame(SystemBackupManager::ARCHIVE_DISK, $backup->archive_disk);
    $this->assertStringNotContainsString('/', (string) $backup->archive_path);
    $this->assertTrue(Storage::disk('backups')->exists($backup->archive_path));
    $this->assertCount(0, Storage::disk('site-exports')->allFiles());
    $this->assertCount(0, Storage::disk('site-transfers')->allFiles());
    $this->assertStringContainsString('Backup archive uploaded and validated successfully.', (string) $backup->output);

    $response->assertRedirect(route('admin.system.backups.show', $backup));

    $listResponse = $this->actingAs($user)->get(route('admin.system.backups.index'));
    $listResponse->assertSee((string) $backup->archive_filename);
    $listResponse->assertSee('downloaded-backup.zip');
    $listResponse->assertSee(SystemBackup::TYPE_UPLOADED);

    $detailResponse = $this->actingAs($user)->get(route('admin.system.backups.show', $backup));
    $detailResponse->assertOk();
    $detailResponse->assertSee('Source filename:');
    $detailResponse->assertSee('downloaded-backup.zip');
    $detailResponse->assertSee('Manifest app:');
    $detailResponse->assertSee('1.2.3');
    $detailResponse->assertSee('Contents:');
    $detailResponse->assertSee('uploads');
    $detailResponse->assertSee(route('admin.system.backups.restore', $backup), false);
  }

  #[Test]
  public function invalid_zip_upload_is_rejected(): void
  {
    Storage::fake('backups');

    $user = User::factory()->superAdmin()->create();
    $path = $this->makeTemporaryDirectory('invalid-upload').'/invalid.zip';
    File::put($path, 'not a zip');

    $response = $this->actingAs($user)->post(route('admin.system.backups.upload.store'), [
      'archive' => new UploadedFile($path, 'invalid.zip', 'application/zip', null, true),
    ]);

    $response->assertStatus(302);
    $response->assertInvalid(['archive']);
    $this->assertDatabaseCount('wbcms_system_backups', 0);
  }

  #[Test]
  public function zip_missing_manifest_is_rejected(): void
  {
    $this->assertRejectedUploadArchive([
      'database/database.sql' => 'select 1;',
    ], 'Backup archive is missing manifest.json.');
  }

  #[Test]
  public function zip_missing_database_sql_is_rejected(): void
  {
    $this->assertRejectedUploadArchive([
      'manifest.json' => json_encode($this->backupManifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    ], 'Backup archive is missing database/database.sql.');
  }

  #[Test]
  public function archive_with_path_traversal_is_rejected(): void
  {
    $this->assertRejectedUploadArchive([
      '../manifest.json' => json_encode($this->backupManifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      'database/database.sql' => 'select 1;',
    ], 'Backup archive contains an invalid entry path.');
  }

  #[Test]
  public function site_export_package_is_rejected_as_not_a_backup_package(): void
  {
    $this->assertRejectedUploadArchive([
      'manifest.json' => json_encode([
        'product' => SiteTransferPackage::PRODUCT,
        'package_type' => SiteTransferPackage::PACKAGE_TYPE,
        'feature_version' => SiteTransferPackage::FEATURE_VERSION,
        'format_version' => SiteTransferPackage::FORMAT_VERSION,
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      'database/database.sql' => 'select 1;',
    ], 'This archive is a site export/import package, not a WebBlocks CMS backup archive.');
  }

  #[Test]
  public function download_route_requires_authentication(): void
  {
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'demo.zip',
      'archive_filename' => 'demo.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    $response = $this->get(route('admin.system.backups.download', $backup));

    $response->assertRedirect(route('webblocks.auth.login'));
  }

  #[Test]
  public function admin_can_download_existing_readable_backup_archive_with_safe_filename(): void
  {
    $backupsRoot = $this->useRealBackupsDiskRoot('download-readable');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'download-readable.zip',
      'archive_filename' => 'download-readable.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    File::put($backupsRoot.'/'.$backup->archive_path, 'zip bytes');

    $response = $this->actingAs($user)->get(route('admin.system.backups.download', $backup));

    $response->assertOk();
    $response->assertDownload('download-readable.zip');
    $this->assertStringContainsString('download-readable.zip', (string) $response->headers->get('content-disposition'));
  }

  #[Test]
  public function backup_download_reports_missing_archive_without_server_error(): void
  {
    $this->useRealBackupsDiskRoot('download-missing');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'missing-download.zip',
      'archive_filename' => 'missing-download.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    $response = $this->actingAs($user)->from(route('admin.system.backups.index'))->get(route('admin.system.backups.download', $backup));

    $response->assertRedirect(route('admin.system.backups.index'));
    $response->assertSessionHasErrors(['system_backup' => 'Backup file not found.']);
  }

  #[Test]
  public function backup_download_reports_unreadable_archive_without_server_error(): void
  {
    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'unreadable.zip',
      'archive_filename' => 'unreadable.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    $this->app->instance(SystemBackupArchiveResolver::class, new class extends SystemBackupArchiveResolver
    {
      public function resolveForBackup(SystemBackup $backup, bool $requireReadableFile = true): SystemBackupArchiveResolution
      {
        return new SystemBackupArchiveResolution(
          SystemBackupArchiveResolution::STATUS_UNREADABLE,
          relativePath: (string) $backup->archive_path,
          message: 'Backup file is not readable.',
        );
      }
    });

    $response = $this->actingAs($user)->from(route('admin.system.backups.index'))->get(route('admin.system.backups.download', $backup));

    $response->assertRedirect(route('admin.system.backups.index'));
    $response->assertSessionHasErrors(['system_backup' => 'Backup file is not readable.']);
  }

  #[Test]
  public function backup_download_blocks_path_traversal_archive_paths(): void
  {
    $backupsRoot = $this->useRealBackupsDiskRoot('download-traversal');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => '../outside.zip',
      'archive_filename' => 'outside.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    File::put(dirname($backupsRoot).'/outside.zip', 'outside');

    $response = $this->actingAs($user)->get(route('admin.system.backups.download', $backup));

    $response->assertForbidden();
  }

  #[Test]
  public function backup_download_blocks_absolute_paths_outside_the_backups_root(): void
  {
    $backupsRoot = $this->useRealBackupsDiskRoot('download-absolute-outside');
    $outsidePath = dirname($backupsRoot).'/outside-absolute.zip';
    File::put($outsidePath, 'outside');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => $outsidePath,
      'archive_filename' => 'outside-absolute.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    $response = $this->actingAs($user)->get(route('admin.system.backups.download', $backup));

    $response->assertForbidden();
  }

  #[Test]
  public function backup_download_supports_legacy_absolute_paths_inside_the_backups_root(): void
  {
    $backupsRoot = $this->useRealBackupsDiskRoot('download-absolute-inside');
    $absolutePath = $backupsRoot.'/legacy-absolute.zip';
    File::put($absolutePath, 'legacy');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => $absolutePath,
      'archive_filename' => 'legacy-absolute.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    $response = $this->actingAs($user)->get(route('admin.system.backups.download', $backup));

    $response->assertOk();
    $response->assertDownload('legacy-absolute.zip');
  }

  #[Test]
  public function backups_index_renders_missing_archive_state_and_disables_download_action(): void
  {
    $this->useRealBackupsDiskRoot('index-missing-download');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'missing-index.zip',
      'archive_filename' => 'missing-index.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    $response = $this->actingAs($user)->get(route('admin.system.backups.index'));

    $response->assertOk();
    $response->assertSee('File missing');
    $response->assertSee('Backup file not found.');
    $response->assertSee('disabled', false);
    $response->assertDontSee('href="'.route('admin.system.backups.download', $backup).'"', false);
  }

  #[Test]
  public function backups_list_renders_view_download_and_delete_actions_for_safe_backups(): void
  {
    Storage::fake('backups');

    $user = User::factory()->superAdmin()->create();
    $manualBackup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'manual.zip',
      'archive_filename' => 'manual.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);
    $uploadedBackup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_UPLOADED,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'uploaded.zip',
      'archive_filename' => 'uploaded.zip',
      'label' => 'source-uploaded.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);
    $restoreSafetyBackup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_RESTORE_SAFETY,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'restore-safety.zip',
      'archive_filename' => 'restore-safety.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);
    $runningBackup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_RUNNING,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'running.zip',
      'archive_filename' => 'running.zip',
      'started_at' => now(),
      'summary' => 'Running.',
    ]);

    foreach ([$manualBackup, $uploadedBackup, $restoreSafetyBackup, $runningBackup] as $backup) {
      Storage::disk('backups')->put((string) $backup->archive_path, 'placeholder');
    }

    $response = $this->actingAs($user)->get(route('admin.system.backups.index'));

    $response->assertOk();
    $response->assertSee(route('admin.system.backups.show', $manualBackup), false);
    $response->assertSee(route('admin.system.backups.download', $manualBackup), false);
    $response->assertSee('action="'.route('admin.system.backups.destroy', $manualBackup).'"', false);
    $response->assertSee('action="'.route('admin.system.backups.destroy', $uploadedBackup).'"', false);
    $response->assertSee('action="'.route('admin.system.backups.destroy', $restoreSafetyBackup).'"', false);
    $response->assertSee('action="'.route('admin.system.backups.destroy', $runningBackup).'"', false);
    $response->assertSee('Delete stuck running backup');
    $response->assertSee('name="force_running" value="1"', false);
    $response->assertSee('name="_method" value="DELETE"', false);
    $response->assertSee('Delete Stuck Running Backup');
    $response->assertSee('Only delete a running backup record when you are sure no backup process is still active.');
    $response->assertSee('Delete Backup');
    $response->assertSee('This deletes the backup record and archive file when present.');
    $response->assertSee('<th>Actions</th>', false);
    $response->assertSee('<td class="wb-table-actions">', false);
    $response->assertSee('<div class="wb-action-group">', false);
    $response->assertSee('data-wb-toggle="modal"', false);
    $response->assertDontSee('onsubmit="return confirm', false);
    $response->assertDontSee('wb-justify-end', false);
    $response->assertDontSee('wb-backup-actions', false);
    $response->assertDontSee('<th>Type</th>', false);
    $response->assertDontSee('<th>Duration</th>', false);
  }

  #[Test]
  public function backups_list_renders_bulk_selection_ui_and_modal_without_browser_confirm(): void
  {
    Storage::fake('backups');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'bulk-ui.zip',
      'archive_filename' => 'bulk-ui.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    $response = $this->actingAs($user)->get(route('admin.system.backups.index'));

    $response->assertOk();
    $response->assertSee('data-wb-admin-bulk-listing', false);
    $response->assertSee('data-wb-admin-select-all-visible', false);
    $response->assertSee('data-wb-admin-row-select', false);
    $response->assertSee('data-wb-admin-bulk-actions', false);
    $response->assertSee('data-wb-target="#bulk-delete-backups-modal"', false);
    $response->assertSee(route('admin.system.backups.bulk-destroy'), false);
    $response->assertSee('name="backup_ids[]"', false);
    $response->assertSee('data-wb-admin-bulk-modal-count', false);
    $response->assertSee('cms/js/admin/listing-bulk-actions.js', false);
    $response->assertSee('selected backups will be deleted.');
    $response->assertSee('value="'.$backup->id.'"', false);
    $response->assertDontSee('confirm(', false);
  }

  #[Test]
  public function bulk_delete_requires_authentication(): void
  {
    $response = $this->delete(route('admin.system.backups.bulk-destroy'), [
      'backup_ids' => [1],
    ]);

    $response->assertRedirect(route('webblocks.auth.login'));
  }

  #[Test]
  public function non_super_admin_cannot_bulk_delete_backups(): void
  {
    Storage::fake('backups');

    $user = User::factory()->editor()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'blocked.zip',
      'archive_filename' => 'blocked.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    $response = $this->actingAs($user)->delete(route('admin.system.backups.bulk-destroy'), [
      'backup_ids' => [$backup->id],
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('wbcms_system_backups', ['id' => $backup->id]);
  }

  #[Test]
  public function super_admin_can_bulk_delete_selected_backups(): void
  {
    $backupsRoot = $this->useRealBackupsDiskRoot('bulk-delete-selected');

    $user = User::factory()->superAdmin()->create();
    $firstBackup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'bulk-first.zip',
      'archive_filename' => 'bulk-first.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);
    $secondBackup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_UPLOADED,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'bulk-second.zip',
      'archive_filename' => 'bulk-second.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);
    $unselectedBackup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'bulk-unselected.zip',
      'archive_filename' => 'bulk-unselected.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    foreach ([$firstBackup, $secondBackup, $unselectedBackup] as $backup) {
      File::put($backupsRoot.'/'.$backup->archive_path, 'placeholder');
    }

    $response = $this->actingAs($user)->delete(route('admin.system.backups.bulk-destroy'), [
      'backup_ids' => [$firstBackup->id, $secondBackup->id],
    ]);

    $response->assertRedirect(route('admin.system.backups.index'));
    $response->assertSessionHas('status', '2 selected backups deleted.');
    $this->assertDatabaseMissing('wbcms_system_backups', ['id' => $firstBackup->id]);
    $this->assertDatabaseMissing('wbcms_system_backups', ['id' => $secondBackup->id]);
    $this->assertDatabaseHas('wbcms_system_backups', ['id' => $unselectedBackup->id]);
    $this->assertFileDoesNotExist($backupsRoot.'/'.$firstBackup->archive_path);
    $this->assertFileDoesNotExist($backupsRoot.'/'.$secondBackup->archive_path);
    $this->assertFileExists($backupsRoot.'/'.$unselectedBackup->archive_path);
  }

  #[Test]
  public function bulk_delete_rejects_missing_or_invalid_ids_safely(): void
  {
    $user = User::factory()->superAdmin()->create();

    $missingResponse = $this->actingAs($user)->from(route('admin.system.backups.index'))->delete(route('admin.system.backups.bulk-destroy'), [
      'backup_ids' => [],
    ]);

    $missingResponse->assertRedirect(route('admin.system.backups.index'));
    $missingResponse->assertSessionHasErrors(['backup_ids']);

    $invalidResponse = $this->actingAs($user)->from(route('admin.system.backups.index'))->delete(route('admin.system.backups.bulk-destroy'), [
      'backup_ids' => [999999],
    ]);

    $invalidResponse->assertRedirect(route('admin.system.backups.index'));
    $invalidResponse->assertSessionHasErrors(['backup_ids.0']);
  }

  #[Test]
  public function bulk_delete_deletes_safe_backups_and_reports_partial_failures(): void
  {
    Storage::fake('backups');

    $user = User::factory()->superAdmin()->create();
    $safeBackup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'bulk-safe.zip',
      'archive_filename' => 'bulk-safe.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);
    $runningBackup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_RUNNING,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'bulk-running.zip',
      'archive_filename' => 'bulk-running.zip',
      'started_at' => now(),
      'summary' => 'Running.',
    ]);

    Storage::disk('backups')->put($safeBackup->archive_path, 'safe');
    Storage::disk('backups')->put($runningBackup->archive_path, 'running');

    $response = $this->actingAs($user)->delete(route('admin.system.backups.bulk-destroy'), [
      'backup_ids' => [$safeBackup->id, $runningBackup->id],
    ]);

    $response->assertRedirect(route('admin.system.backups.index'));
    $response->assertSessionHas('status', '1 selected backup deleted. 1 could not be deleted.');
    $response->assertSessionHasErrors(['system_backup']);
    $this->assertDatabaseMissing('wbcms_system_backups', ['id' => $safeBackup->id]);
    $this->assertDatabaseHas('wbcms_system_backups', ['id' => $runningBackup->id]);
    $this->assertFalse(Storage::disk('backups')->exists($safeBackup->archive_path));
    $this->assertTrue(Storage::disk('backups')->exists($runningBackup->archive_path));
  }

  #[Test]
  public function stale_running_backup_is_marked_failed_when_backups_page_loads(): void
  {
    config()->set('cms.backup.stale_after_minutes', 10);
    Storage::fake('backups');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_RUNNING,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'stale-running.zip',
      'archive_filename' => 'stale-running.zip',
      'started_at' => now()->subMinutes(20),
      'summary' => 'Running.',
      'output' => 'Backup started.',
    ]);

    $backup->forceFill([
      'created_at' => now()->subMinutes(20),
      'updated_at' => now()->subMinutes(20),
    ])->saveQuietly();

    $response = $this->actingAs($user)->get(route('admin.system.backups.index'));

    $response->assertOk();

    $backup->refresh();

    $this->assertSame(SystemBackup::STATUS_FAILED, $backup->status);
    $this->assertNotNull($backup->finished_at);
    $this->assertSame('This backup did not finish in time and was marked as failed.', $backup->summary);
    $this->assertSame('This backup did not finish in time and was marked as failed. You can delete this failed backup record or create a fresh backup.', $backup->error_message);
    $this->assertStringContainsString('Backup started.', (string) $backup->output);
    $this->assertStringContainsString('This backup did not finish in time and was marked as failed.', (string) $backup->output);

    $page = $this->actingAs($user)->get(route('admin.system.backups.index'));

    $page->assertSee('Latest failure');
    $page->assertSee('This backup did not finish in time and was marked as failed. You can delete this failed backup record or create a fresh backup.');
  }

  #[Test]
  public function admin_can_delete_completed_manual_backup_record_and_archive(): void
  {
    $backupsRoot = $this->useRealBackupsDiskRoot('delete-manual-relative');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'manual.zip',
      'archive_filename' => 'manual.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    File::put($backupsRoot.'/'.$backup->archive_path, 'placeholder');

    $response = $this->actingAs($user)
      ->from(route('admin.system.backups.index'))
      ->delete(route('admin.system.backups.destroy', $backup));

    $response->assertRedirect(route('admin.system.backups.index'));
    $response->assertSessionHas('status', 'Backup deleted.');
    $this->assertDatabaseMissing('wbcms_system_backups', ['id' => $backup->id]);
    $this->assertFileDoesNotExist($backupsRoot.'/manual.zip');
  }

  #[Test]
  public function admin_can_delete_uploaded_backup_record_and_archive(): void
  {
    $backupsRoot = $this->useRealBackupsDiskRoot('delete-uploaded-relative');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_UPLOADED,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'uploaded.zip',
      'archive_filename' => 'uploaded.zip',
      'label' => 'source-uploaded.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    File::put($backupsRoot.'/'.$backup->archive_path, 'placeholder');

    $response = $this->actingAs($user)
      ->from(route('admin.system.backups.index'))
      ->delete(route('admin.system.backups.destroy', $backup));

    $response->assertRedirect(route('admin.system.backups.index'));
    $response->assertSessionHas('status', 'Backup deleted.');
    $this->assertDatabaseMissing('wbcms_system_backups', ['id' => $backup->id]);
    $this->assertFileDoesNotExist($backupsRoot.'/uploaded.zip');
  }

  #[Test]
  public function deleting_backup_succeeds_even_when_the_archive_file_is_already_missing(): void
  {
    $this->useRealBackupsDiskRoot('delete-missing-archive');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'missing.zip',
      'archive_filename' => 'missing.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    $response = $this->actingAs($user)
      ->from(route('admin.system.backups.index'))
      ->delete(route('admin.system.backups.destroy', $backup));

    $response->assertRedirect(route('admin.system.backups.index'));
    $response->assertSessionHas('status', 'Backup deleted.');
    $this->assertDatabaseMissing('wbcms_system_backups', ['id' => $backup->id]);
  }

  #[Test]
  public function deleting_one_backup_does_not_delete_another_backups_archive_file(): void
  {
    $backupsRoot = $this->useRealBackupsDiskRoot('delete-one-of-two');

    $user = User::factory()->superAdmin()->create();
    $firstBackup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'first.zip',
      'archive_filename' => 'first.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);
    $secondBackup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'second.zip',
      'archive_filename' => 'second.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    File::put($backupsRoot.'/'.$firstBackup->archive_path, 'first');
    File::put($backupsRoot.'/'.$secondBackup->archive_path, 'second');

    $response = $this->actingAs($user)
      ->from(route('admin.system.backups.index'))
      ->delete(route('admin.system.backups.destroy', $firstBackup));

    $response->assertRedirect(route('admin.system.backups.index'));
    $this->assertDatabaseMissing('wbcms_system_backups', ['id' => $firstBackup->id]);
    $this->assertDatabaseHas('wbcms_system_backups', ['id' => $secondBackup->id]);
    $this->assertFileDoesNotExist($backupsRoot.'/'.$firstBackup->archive_path);
    $this->assertFileExists($backupsRoot.'/'.$secondBackup->archive_path);
  }

  #[Test]
  public function deleting_backup_with_unsafe_archive_path_preserves_record_and_reports_a_safe_error(): void
  {
    $backupsRoot = $this->useRealBackupsDiskRoot('delete-unsafe-path');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => '../outside.zip',
      'archive_filename' => 'outside.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    File::put(dirname($backupsRoot).'/outside.zip', 'do not delete');

    $response = $this->actingAs($user)
      ->from(route('admin.system.backups.index'))
      ->delete(route('admin.system.backups.destroy', $backup));

    $response->assertRedirect(route('admin.system.backups.index'));
    $response->assertSessionHasErrors(['system_backup' => 'Backup archive path is invalid.']);
    $this->assertDatabaseHas('wbcms_system_backups', ['id' => $backup->id]);
    $this->assertFileExists(dirname($backupsRoot).'/outside.zip');
  }

  #[Test]
  public function deleting_backup_with_absolute_path_outside_backups_root_preserves_record_and_file(): void
  {
    $backupsRoot = $this->useRealBackupsDiskRoot('delete-absolute-outside');
    $outsidePath = dirname($backupsRoot).'/outside-delete.zip';
    File::put($outsidePath, 'do not delete');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => $outsidePath,
      'archive_filename' => 'outside-delete.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    $response = $this->actingAs($user)
      ->from(route('admin.system.backups.index'))
      ->delete(route('admin.system.backups.destroy', $backup));

    $response->assertRedirect(route('admin.system.backups.index'));
    $response->assertSessionHasErrors(['system_backup' => 'Backup archive path is invalid.']);
    $this->assertDatabaseHas('wbcms_system_backups', ['id' => $backup->id]);
    $this->assertFileExists($outsidePath);
  }

  #[Test]
  public function admin_can_delete_failed_backup_record_and_archive(): void
  {
    $backupsRoot = $this->useRealBackupsDiskRoot('delete-failed');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_FAILED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'failed.zip',
      'archive_filename' => 'failed.zip',
      'started_at' => now()->subMinutes(1),
      'finished_at' => now(),
      'summary' => 'Backup failed.',
      'error_message' => 'Backup failed.',
    ]);

    File::put($backupsRoot.'/'.$backup->archive_path, 'placeholder');

    $response = $this->actingAs($user)
      ->from(route('admin.system.backups.index'))
      ->delete(route('admin.system.backups.destroy', $backup));

    $response->assertRedirect(route('admin.system.backups.index'));
    $response->assertSessionHas('status', 'Backup deleted.');
    $this->assertDatabaseMissing('wbcms_system_backups', ['id' => $backup->id]);
    $this->assertFileDoesNotExist($backupsRoot.'/failed.zip');
  }

  #[Test]
  public function admin_can_delete_restore_safety_backup_record_and_archive(): void
  {
    $backupsRoot = $this->useRealBackupsDiskRoot('delete-restore-safety');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_RESTORE_SAFETY,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'restore-safety.zip',
      'archive_filename' => 'restore-safety.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    File::put($backupsRoot.'/'.$backup->archive_path, 'placeholder');

    $response = $this->actingAs($user)
      ->from(route('admin.system.backups.index'))
      ->delete(route('admin.system.backups.destroy', $backup));

    $response->assertRedirect(route('admin.system.backups.index'));
    $response->assertSessionHas('status', 'Backup deleted.');
    $this->assertDatabaseMissing('wbcms_system_backups', ['id' => $backup->id]);
    $this->assertFileDoesNotExist($backupsRoot.'/restore-safety.zip');
  }

  #[Test]
  public function running_backup_cannot_be_deleted_without_force_running(): void
  {
    Storage::fake('backups');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_RUNNING,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'running.zip',
      'archive_filename' => 'running.zip',
      'started_at' => now(),
      'summary' => 'Running.',
    ]);

    Storage::disk('backups')->put($backup->archive_path, 'placeholder');

    $response = $this->actingAs($user)
      ->from(route('admin.system.backups.index'))
      ->delete(route('admin.system.backups.destroy', $backup));

    $response->assertRedirect(route('admin.system.backups.index'));
    $response->assertSessionHasErrors(['system_backup' => 'Running backup cannot be deleted unless you explicitly confirm it is stuck.']);
    $this->assertDatabaseHas('wbcms_system_backups', ['id' => $backup->id]);
    $this->assertTrue(Storage::disk('backups')->exists($backup->archive_path));
  }

  #[Test]
  public function running_backup_can_be_deleted_with_force_running(): void
  {
    $backupsRoot = $this->useRealBackupsDiskRoot('delete-running-force');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_RUNNING,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'running.zip',
      'archive_filename' => 'running.zip',
      'started_at' => now(),
      'summary' => 'Running.',
    ]);

    File::put($backupsRoot.'/'.$backup->archive_path, 'placeholder');

    $response = $this->actingAs($user)->call('DELETE', route('admin.system.backups.destroy', $backup), [
      'force_running' => '1',
    ]);

    $response->assertRedirect(route('admin.system.backups.index'));
    $response->assertSessionHas('status', 'Stuck running backup record deleted.');
    $this->assertDatabaseMissing('wbcms_system_backups', ['id' => $backup->id]);
    $this->assertFileDoesNotExist($backupsRoot.'/running.zip');
  }

  #[Test]
  public function stale_running_backup_can_be_deleted(): void
  {
    config()->set('cms.backup.stale_after_minutes', 10);
    $backupsRoot = $this->useRealBackupsDiskRoot('delete-stale-running');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_RUNNING,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'stale-running-delete.zip',
      'archive_filename' => 'stale-running-delete.zip',
      'started_at' => now()->subMinutes(20),
      'summary' => 'Running.',
    ]);

    $backup->forceFill([
      'created_at' => now()->subMinutes(20),
      'updated_at' => now()->subMinutes(20),
    ])->saveQuietly();

    File::put($backupsRoot.'/'.$backup->archive_path, 'placeholder');

    $response = $this->actingAs($user)
      ->from(route('admin.system.backups.index'))
      ->delete(route('admin.system.backups.destroy', $backup));

    $response->assertRedirect(route('admin.system.backups.index'));
    $response->assertSessionHas('status', 'Backup deleted.');
    $this->assertDatabaseMissing('wbcms_system_backups', ['id' => $backup->id]);
    $this->assertFileDoesNotExist($backupsRoot.'/stale-running-delete.zip');
  }

  #[Test]
  public function ui_delete_action_for_real_created_backup_removes_the_physical_zip_file(): void
  {
    Storage::fake('public');
    $backupsRoot = $this->useRealBackupsDiskRoot('delete-real-created-backup');

    $user = User::factory()->superAdmin()->create();

    $createResponse = $this->actingAs($user)->post(route('admin.system.backups.store'));
    $createResponse->assertRedirect(route('admin.system.backups.index'));

    $backup = SystemBackup::query()->latest()->first();

    $this->assertNotNull($backup);
    $this->assertNotNull($backup->archive_path);
    $absoluteArchivePath = $backupsRoot.'/'.$backup->archive_path;
    $this->assertFileExists($absoluteArchivePath);

    $deleteResponse = $this->actingAs($user)
      ->from(route('admin.system.backups.index'))
      ->delete(route('admin.system.backups.destroy', $backup));

    $deleteResponse->assertRedirect(route('admin.system.backups.index'));
    $deleteResponse->assertSessionHas('status', 'Backup deleted.');
    $this->assertDatabaseMissing('wbcms_system_backups', ['id' => $backup->id]);
    $this->assertFileDoesNotExist($absoluteArchivePath);
  }

  #[Test]
  public function ui_delete_action_for_real_created_backup_uses_the_same_real_path_format_as_backup_creation(): void
  {
    Storage::fake('public');
    $backupsRoot = $this->useRealBackupsDiskRoot('delete-real-created-backup-format');

    $user = User::factory()->superAdmin()->create();

    $createResponse = $this->actingAs($user)->post(route('admin.system.backups.store'));
    $createResponse->assertRedirect(route('admin.system.backups.index'));

    $backup = SystemBackup::query()->latest()->first();

    $this->assertNotNull($backup);
    $this->assertSame('backups', $backup->archive_disk);
    $this->assertMatchesRegularExpression(
      '/^webblocks-cms-backup-\d{4}-\d{2}-\d{2}-\d{6}\.zip$/',
      (string) $backup->archive_path,
    );
    $this->assertStringNotContainsString('/', (string) $backup->archive_path);

    $absoluteArchivePath = $backupsRoot.'/'.$backup->archive_path;
    $this->assertFileExists($absoluteArchivePath);

    $deleteResponse = $this->actingAs($user)
      ->from(route('admin.system.backups.index'))
      ->delete(route('admin.system.backups.destroy', $backup));

    $deleteResponse->assertRedirect(route('admin.system.backups.index'));
    $deleteResponse->assertSessionHas('status', 'Backup deleted.');
    $this->assertDatabaseMissing('wbcms_system_backups', ['id' => $backup->id]);
    $this->assertFileDoesNotExist($absoluteArchivePath);
  }

  #[Test]
  public function backup_manager_logs_context_and_preserves_record_when_archive_delete_fails(): void
  {
    $backupsRoot = $this->useRealBackupsDiskRoot('delete-log-failure');

    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'cannot-delete.zip',
      'archive_filename' => 'cannot-delete.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    $archivePath = $backupsRoot.'/cannot-delete.zip';
    File::put($archivePath, 'placeholder');

    Log::spy();
    $manager = app(SystemBackupManager::class);
    chmod(dirname($archivePath), 0555);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Backup archive file could not be deleted.');

    try {
      $manager->deleteBackupRecord($backup, true);
    } finally {
      chmod(dirname($archivePath), 0755);
      $this->assertDatabaseHas('wbcms_system_backups', ['id' => $backup->id]);
      Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context = []) => $message === 'Backup archive file could not be deleted.'
          && ($context['backup_id'] ?? null) === $backup->id
          && ($context['archive_disk'] ?? null) === SystemBackupManager::ARCHIVE_DISK
          && ($context['archive_status'] ?? null) === SystemBackupArchiveResolution::STATUS_AVAILABLE
          && ! array_key_exists('disk_root', $context));
    }
  }

  #[Test]
  public function restore_history_entry_can_be_deleted_without_deleting_backup_record_or_archive(): void
  {
    Storage::fake('backups');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'source.zip',
      'archive_filename' => 'source.zip',
      'started_at' => now()->subMinutes(10),
      'finished_at' => now()->subMinutes(9),
      'summary' => 'Completed.',
    ]);
    $restore = SystemBackupRestore::query()->create([
      'source_backup_id' => $backup->id,
      'source_archive_disk' => 'backups',
      'source_archive_path' => $backup->archive_path,
      'source_archive_filename' => $backup->archive_filename,
      'status' => SystemBackupRestore::STATUS_FAILED,
      'restored_parts' => ['database', 'uploads'],
      'started_at' => now()->subMinutes(8),
      'finished_at' => now()->subMinutes(7),
      'summary' => 'Restore failed.',
      'error_message' => 'Restore failed.',
    ]);

    Storage::disk('backups')->put($backup->archive_path, 'placeholder');

    $response = $this->actingAs($user)->delete(route('admin.system.backups.restores.destroy', [$backup, $restore]));

    $response->assertRedirect(route('admin.system.backups.show', $backup));
    $response->assertSessionHas('status', 'Restore history entry deleted.');
    $this->assertDatabaseMissing('wbcms_system_backup_restores', ['id' => $restore->id]);
    $this->assertDatabaseHas('wbcms_system_backups', ['id' => $backup->id]);
    $this->assertTrue(Storage::disk('backups')->exists($backup->archive_path));
  }

  #[Test]
  public function restore_history_entry_cannot_be_deleted_through_a_different_backup_url(): void
  {
    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'source.zip',
      'archive_filename' => 'source.zip',
      'started_at' => now()->subMinutes(10),
      'finished_at' => now()->subMinutes(9),
      'summary' => 'Completed.',
    ]);
    $otherBackup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'backups',
      'archive_path' => 'other.zip',
      'archive_filename' => 'other.zip',
      'started_at' => now()->subMinutes(6),
      'finished_at' => now()->subMinutes(5),
      'summary' => 'Completed.',
    ]);
    $restore = SystemBackupRestore::query()->create([
      'source_backup_id' => $backup->id,
      'source_archive_disk' => 'backups',
      'source_archive_path' => $backup->archive_path,
      'source_archive_filename' => $backup->archive_filename,
      'status' => SystemBackupRestore::STATUS_COMPLETED,
      'restored_parts' => ['database'],
      'started_at' => now()->subMinutes(8),
      'finished_at' => now()->subMinutes(7),
      'summary' => 'Restore completed.',
    ]);

    $response = $this->actingAs($user)->delete(route('admin.system.backups.restores.destroy', [$otherBackup, $restore]));

    $response->assertNotFound();
    $this->assertDatabaseHas('wbcms_system_backup_restores', ['id' => $restore->id]);
  }

  #[Test]
  public function restore_invalid_sql_dump_shows_single_clear_error_and_skips_duplicate_validation_alert(): void
  {
    Storage::fake('backups');

    $user = User::factory()->superAdmin()->create();
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_UPLOADED,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => false,
      'archive_disk' => 'backups',
      'archive_path' => 'invalid-sql.zip',
      'archive_filename' => 'invalid-sql.zip',
      'label' => 'invalid-sql.zip',
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    $this->createBackupArchive($backup->archive_path, [
      'manifest.json' => json_encode(array_merge($this->backupManifest(), [
        'included_parts' => [
          'database' => true,
          'uploads' => false,
        ],
      ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      'database/database.sql' => "You executed `mysqldump --single-transaction demo`\n",
    ]);

    $response = $this->actingAs($user)->post(route('admin.system.backups.restore', $backup), [
      'acknowledge_restore_risk' => '1',
    ]);

    $response->assertRedirect(route('admin.system.backups.show', $backup));

    $page = $this->actingAs($user)->get(route('admin.system.backups.show', $backup));

    $page->assertSee('Restore Failed');
    $page->assertSee('Backup archive database/database.sql contains command output instead of SQL.');
    $page->assertDontSee('System restore completed successfully.');
    $page->assertDontSee('Validation Error');
  }

  #[Test]
  public function backup_download_and_delete_ignore_wrong_recorded_archive_disk_and_use_backups_disk_only(): void
  {
    Storage::fake('backups');
    Storage::fake('site-exports');

    $user = User::factory()->superAdmin()->create();
    $archivePath = 'recorded-on-wrong-disk.zip';

    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'site-exports',
      'archive_path' => $archivePath,
      'archive_filename' => $archivePath,
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    $this->createBackupArchive($archivePath, [
      'manifest.json' => json_encode($this->backupManifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      'database/database.sql' => 'select 1;',
    ]);
    Storage::disk('site-exports')->put($archivePath, 'wrong-disk-copy');

    $download = $this->actingAs($user)->get(route('admin.system.backups.download', $backup));

    $download->assertOk();
    $download->assertDownload($archivePath);
    $this->assertTrue(Storage::disk('backups')->exists($archivePath));

    $deleteResponse = $this->actingAs($user)->delete(route('admin.system.backups.destroy', $backup));

    $deleteResponse->assertRedirect(route('admin.system.backups.index'));
    $this->assertFalse(Storage::disk('backups')->exists($archivePath));
    $this->assertTrue(Storage::disk('site-exports')->exists($archivePath));
    $this->assertDatabaseMissing('wbcms_system_backups', ['id' => $backup->id]);
  }

  #[Test]
  public function backup_show_uses_backups_disk_even_if_recorded_disk_is_wrong(): void
  {
    Storage::fake('backups');
    Storage::fake('site-exports');

    $user = User::factory()->superAdmin()->create();
    $archivePath = 'wrong-disk-detail.zip';
    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL,
      'status' => SystemBackup::STATUS_COMPLETED,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => 'site-exports',
      'archive_path' => $archivePath,
      'archive_filename' => $archivePath,
      'started_at' => now(),
      'finished_at' => now(),
      'summary' => 'Completed.',
    ]);

    $this->createBackupArchive($archivePath, [
      'manifest.json' => json_encode($this->backupManifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      'database/database.sql' => 'select 1;',
    ]);
    Storage::disk('site-exports')->put($archivePath, 'wrong-disk-copy');

    $response = $this->actingAs($user)->get(route('admin.system.backups.show', $backup));

    $response->assertOk();
    $response->assertSee((string) $backup->archive_filename);
    $response->assertSee('Restore backup');
  }

  protected function tearDown(): void
  {
    foreach ($this->temporaryDirectories as $directory) {
      File::deleteDirectory($directory);
    }

    Mockery::close();

    parent::tearDown();
  }

  private function assertRejectedUploadArchive(array $entries, string $message): void
  {
    Storage::fake('backups');

    $user = User::factory()->superAdmin()->create();
    $uploadedFile = $this->makeUploadedBackupArchive('broken-backup.zip', $entries);

    $response = $this->actingAs($user)->post(route('admin.system.backups.upload.store'), [
      'archive' => $uploadedFile,
    ]);

    $response->assertRedirect(route('admin.system.backups.upload'));
    $response->assertSessionHasErrors(['system_backup' => $message]);
    $this->assertDatabaseCount('wbcms_system_backups', 0);
    $this->assertCount(0, Storage::disk('backups')->allFiles());
  }

  private function makeUploadedBackupArchive(string $clientName, array $entries): UploadedFile
  {
    $directory = $this->makeTemporaryDirectory('uploaded-backup');
    $path = $directory.'/'.$clientName;
    $archive = new ZipArchive;

    $this->assertTrue($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);

    foreach ($entries as $entryPath => $contents) {
      $archive->addFromString($entryPath, $contents);
    }

    $archive->close();

    return new UploadedFile($path, $clientName, 'application/zip', null, true);
  }

  private function createBackupArchive(string $relativePath, array $entries): void
  {
    $path = Storage::disk('backups')->path($relativePath);
    File::ensureDirectoryExists(dirname($path));

    $archive = new ZipArchive;
    $this->assertTrue($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);

    foreach ($entries as $entryPath => $contents) {
      $archive->addFromString($entryPath, $contents);
    }

    $archive->close();
  }

  private function backupManifest(): array
  {
    return [
      'product' => SystemBackupArchivePackage::PRODUCT,
      'package_type' => SystemBackupArchivePackage::PACKAGE_TYPE,
      'feature_version' => SystemBackupArchivePackage::FEATURE_VERSION,
      'format_version' => SystemBackupArchivePackage::FORMAT_VERSION,
      'app_name' => 'WebBlocks CMS',
      'backup_id' => 1,
      'backup_type' => 'manual',
      'included_parts' => [
        'database' => true,
        'uploads' => true,
      ],
      'archive_format' => 'zip',
    ];
  }

  private function makeTemporaryDirectory(string $prefix): string
  {
    $path = storage_path('app/testing-system-backups/'.$prefix.'-'.Str::uuid());
    File::ensureDirectoryExists($path);
    $this->temporaryDirectories[] = $path;

    return $path;
  }

  private function useRealExportsDiskRoot(string $prefix): string
  {
    $path = $this->makeTemporaryDirectory($prefix);
    config()->set('filesystems.disks.site-exports.root', $path);

    return $path;
  }

  private function useRealBackupsDiskRoot(string $prefix): string
  {
    $path = $this->makeTemporaryDirectory($prefix);
    config()->set('filesystems.disks.backups.root', $path);

    return $path;
  }

  private function assertStringOrder(string $content, string $firstNeedle, string $secondNeedle): void
  {
    $firstPosition = strpos($content, $firstNeedle);
    $secondPosition = strpos($content, $secondNeedle);

    $this->assertNotFalse($firstPosition, "Failed asserting that [{$firstNeedle}] exists in the response.");
    $this->assertNotFalse($secondPosition, "Failed asserting that [{$secondNeedle}] exists in the response.");
    $this->assertLessThan($secondPosition, $firstPosition, "Failed asserting that [{$firstNeedle}] appears before [{$secondNeedle}].");
  }

  private function makeRestoreResult(SystemBackup $backup): BackupRestoreResult
  {
    return new BackupRestoreResult(
      sourceBackup: $backup,
      sourceArchivePath: (string) $backup->archive_path,
      sourceArchiveFilename: (string) $backup->archive_filename,
      inspection: new BackupRestoreInspection(
        manifest: $this->backupManifest(),
        includesDatabase: true,
        includesUploads: true,
        databaseSqlPath: 'database/database.sql',
        uploadsRootPath: 'uploads/public',
      ),
      safetyBackup: null,
      output: ['Restore completed.'],
      restoreRecord: null,
    );
  }
}
