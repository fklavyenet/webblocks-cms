<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\SystemBackup;
use App\Models\SystemBackupRestore;
use App\Models\User;
use App\Support\Sites\ExportImport\SiteTransferPackage;
use App\Support\System\BackupRestoreInspection;
use App\Support\System\BackupRestoreResult;
use App\Support\System\DatabaseDumpWriter;
use App\Support\System\SystemBackupArchivePackage;
use App\Support\System\SystemBackupManager;
use App\Support\System\SystemBackupRestoreManager;
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
        $response->assertDontSee('System Updates');
        $response->assertDontSee('>Cancel<', false);
        $response->assertSee('No backup history yet');
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
        $this->assertStringContainsString('<div class="wb-page-actions">', $content);
        $this->assertStringContainsString(route('admin.system.backups.upload'), $content);
        $this->assertStringNotContainsString('<div class="wb-page-actions"><div class="wb-cluster wb-cluster-2"><a href="'.route('admin.system.updates.index'), $content);
        $this->assertSame(1, substr_count($content, 'Upload backup'));
    }

    #[Test]
    public function recommendation_card_only_shows_create_backup_when_a_recent_backup_is_not_available(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.system.backups.index'));

        $response->assertOk();
        $response->assertSee('Backup Recommendation');
        $this->assertSame(2, substr_count($response->getContent(), 'Create backup'));
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
        Schema::drop('system_backups');

        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.system.backups.index'));

        $response->assertOk();
        $response->assertSee('Backups');
        $response->assertSee('Backup storage is not ready yet');
        $response->assertSee('system_backups');
    }

    #[Test]
    public function admin_can_create_backup_record_and_download_artifact(): void
    {
        $backupsRoot = $this->useRealBackupsDiskRoot('create-backup-artifact');
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
        $this->assertNotNull($backup->archive_path);

        $this->assertStringNotContainsString('/', (string) $backup->archive_path);
        $this->assertFileExists($backupsRoot.'/'.$backup->archive_path);

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

        $statement = $snapshotConnection->prepare('select status, finished_at, archive_path, archive_filename from system_backups where id = :id');
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
        $this->assertDatabaseMissing('system_backups', ['id' => $backup->id]);
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
        $this->assertStringNotContainsString('/', (string) $backup->archive_path);
        $this->assertTrue(Storage::disk('backups')->exists($backup->archive_path));
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
        $this->assertDatabaseCount('system_backups', 0);
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

        $response->assertRedirect(route('login'));
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
        $response->assertSee('This backup is marked as running. Delete this stuck backup record anyway? Only do this if no backup process is still active.');
        $response->assertSee('Delete this backup record and archive file? This cannot be undone.');
        $response->assertSee('<th>Actions</th>', false);
        $response->assertSee('<div class="wb-action-group">', false);
        $response->assertDontSee('wb-justify-end', false);
        $response->assertDontSee('wb-backup-actions', false);
        $response->assertDontSee('<th>Type</th>', false);
        $response->assertDontSee('<th>Duration</th>', false);
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
        $this->assertDatabaseMissing('system_backups', ['id' => $backup->id]);
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
        $this->assertDatabaseMissing('system_backups', ['id' => $backup->id]);
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
        $this->assertDatabaseMissing('system_backups', ['id' => $backup->id]);
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
        $this->assertDatabaseMissing('system_backups', ['id' => $firstBackup->id]);
        $this->assertDatabaseHas('system_backups', ['id' => $secondBackup->id]);
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
        $this->assertDatabaseHas('system_backups', ['id' => $backup->id]);
        $this->assertFileExists(dirname($backupsRoot).'/outside.zip');
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
        $this->assertDatabaseMissing('system_backups', ['id' => $backup->id]);
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
        $this->assertDatabaseMissing('system_backups', ['id' => $backup->id]);
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
        $this->assertDatabaseHas('system_backups', ['id' => $backup->id]);
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
        $this->assertDatabaseMissing('system_backups', ['id' => $backup->id]);
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
        $this->assertDatabaseMissing('system_backups', ['id' => $backup->id]);
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
        $this->assertDatabaseMissing('system_backups', ['id' => $backup->id]);
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
        $this->assertDatabaseMissing('system_backups', ['id' => $backup->id]);
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
            $this->assertDatabaseHas('system_backups', ['id' => $backup->id]);
            Log::shouldHaveReceived('warning')
                ->once()
                ->withArgs(fn (string $message, array $context = []) => $message === 'Backup archive file could not be deleted.'
                    && ($context['backup_id'] ?? null) === $backup->id
                    && ($context['archive_path'] ?? null) === 'cannot-delete.zip'
                    && ($context['disk_root'] ?? null) === $backupsRoot);
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
        $this->assertDatabaseMissing('system_backup_restores', ['id' => $restore->id]);
        $this->assertDatabaseHas('system_backups', ['id' => $backup->id]);
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
        $this->assertDatabaseHas('system_backup_restores', ['id' => $restore->id]);
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
            'database/database.sql' => "You executed `ddev exec --raw -- mysqldump --single-transaction demo`\n",
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
        $this->assertDatabaseCount('system_backups', 0);
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

    private function useRealBackupsDiskRoot(string $prefix): string
    {
        $path = $this->makeTemporaryDirectory($prefix);
        config()->set('filesystems.disks.backups.root', $path);

        return $path;
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
