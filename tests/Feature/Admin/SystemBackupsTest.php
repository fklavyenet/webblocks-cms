<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\SystemBackup;
use App\Models\User;
use App\Support\Sites\ExportImport\SiteTransferPackage;
use App\Support\System\DatabaseDumpWriter;
use App\Support\System\SystemBackupArchivePackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
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
        Storage::fake('public');
        Storage::fake('backups');

        $user = User::factory()->superAdmin()->create();
        $folder = AssetFolder::query()->create(['name' => 'Docs', 'slug' => 'docs']);

        Storage::disk('public')->put('media/documents/readme.txt', 'backup me');

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

        $response->assertRedirect(route('admin.system.backups.index'));

        $backup = SystemBackup::query()->latest()->first();

        $this->assertNotNull($backup);
        $this->assertSame(SystemBackup::STATUS_COMPLETED, $backup->status);
        $this->assertSame(SystemBackup::TYPE_MANUAL, $backup->type);
        $this->assertNotNull($backup->archive_path);

        $this->assertTrue(Storage::disk('backups')->exists($backup->archive_path));

        $archivePath = Storage::disk('backups')->path($backup->archive_path);
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
    public function failed_backup_is_recorded_as_failed(): void
    {
        Storage::fake('public');
        Storage::fake('backups');

        $user = User::factory()->superAdmin()->create();
        $mock = Mockery::mock(DatabaseDumpWriter::class);
        $mock->shouldReceive('dumpTo')->once()->andThrow(new \RuntimeException('mysqldump is not available.'));
        $this->app->instance(DatabaseDumpWriter::class, $mock);

        $response = $this->actingAs($user)->post(route('admin.system.backups.store'));

        $response->assertRedirect(route('admin.system.backups.index'));

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
            'archive_path' => '2026/04/20/demo.zip',
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
        $this->assertStringStartsWith('uploaded/', (string) $backup->archive_path);
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
        $detailResponse->assertSee('DB + uploads');
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
        $this->assertCount(0, Storage::disk('backups')->allFiles());
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
            'archive_path' => '2026/04/17/demo.zip',
            'archive_filename' => 'demo.zip',
            'started_at' => now(),
            'finished_at' => now(),
            'summary' => 'Completed.',
        ]);

        $response = $this->get(route('admin.system.backups.download', $backup));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function backups_list_shows_delete_action_for_failed_and_running_backups_only(): void
    {
        $user = User::factory()->superAdmin()->create();
        $failedBackup = SystemBackup::query()->create([
            'type' => SystemBackup::TYPE_MANUAL,
            'status' => SystemBackup::STATUS_FAILED,
            'includes_database' => true,
            'includes_uploads' => true,
            'archive_disk' => 'backups',
            'archive_path' => '2026/04/20/failed.zip',
            'archive_filename' => 'failed.zip',
            'started_at' => now(),
            'finished_at' => now(),
            'summary' => 'Failed.',
            'error_message' => 'Failed.',
        ]);
        $runningBackup = SystemBackup::query()->create([
            'type' => SystemBackup::TYPE_MANUAL,
            'status' => SystemBackup::STATUS_RUNNING,
            'includes_database' => true,
            'includes_uploads' => true,
            'archive_disk' => 'backups',
            'archive_path' => '2026/04/20/running.zip',
            'archive_filename' => 'running.zip',
            'started_at' => now(),
            'summary' => 'Running.',
        ]);
        $completedBackup = SystemBackup::query()->create([
            'type' => SystemBackup::TYPE_MANUAL,
            'status' => SystemBackup::STATUS_COMPLETED,
            'includes_database' => true,
            'includes_uploads' => true,
            'archive_disk' => 'backups',
            'archive_path' => '2026/04/20/completed.zip',
            'archive_filename' => 'completed.zip',
            'started_at' => now(),
            'finished_at' => now(),
            'summary' => 'Completed.',
        ]);

        $response = $this->actingAs($user)->get(route('admin.system.backups.index'));

        $response->assertOk();
        $response->assertSee(route('admin.system.backups.destroy', $failedBackup), false);
        $response->assertSee(route('admin.system.backups.destroy', $runningBackup), false);
        $response->assertSee('action="'.route('admin.system.backups.destroy', $failedBackup).'"', false);
        $response->assertSee('action="'.route('admin.system.backups.destroy', $runningBackup).'"', false);
        $response->assertDontSee('action="'.route('admin.system.backups.destroy', $completedBackup).'"', false);
        $response->assertSee('Delete this backup record? This action cannot be undone.');
    }

    #[Test]
    public function admin_can_delete_failed_backup_record_and_archive(): void
    {
        Storage::fake('backups');

        $user = User::factory()->superAdmin()->create();
        $backup = SystemBackup::query()->create([
            'type' => SystemBackup::TYPE_MANUAL,
            'status' => SystemBackup::STATUS_FAILED,
            'includes_database' => true,
            'includes_uploads' => true,
            'archive_disk' => 'backups',
            'archive_path' => '2026/04/20/failed.zip',
            'archive_filename' => 'failed.zip',
            'started_at' => now(),
            'finished_at' => now(),
            'summary' => 'Failed.',
            'error_message' => 'Failed.',
        ]);

        Storage::disk('backups')->put($backup->archive_path, 'placeholder');

        $response = $this->actingAs($user)->delete(route('admin.system.backups.destroy', $backup));

        $response->assertRedirect(route('admin.system.backups.index'));
        $response->assertSessionHas('status', 'Backup record deleted.');
        $this->assertDatabaseMissing('system_backups', ['id' => $backup->id]);
        $this->assertFalse(Storage::disk('backups')->exists($backup->archive_path));
    }

    #[Test]
    public function admin_can_delete_running_backup_record_and_archive(): void
    {
        Storage::fake('backups');

        $user = User::factory()->superAdmin()->create();
        $backup = SystemBackup::query()->create([
            'type' => SystemBackup::TYPE_MANUAL,
            'status' => SystemBackup::STATUS_RUNNING,
            'includes_database' => true,
            'includes_uploads' => true,
            'archive_disk' => 'backups',
            'archive_path' => '2026/04/20/running.zip',
            'archive_filename' => 'running.zip',
            'started_at' => now(),
            'summary' => 'Running.',
        ]);

        Storage::disk('backups')->put($backup->archive_path, 'placeholder');

        $response = $this->actingAs($user)->delete(route('admin.system.backups.destroy', $backup));

        $response->assertRedirect(route('admin.system.backups.index'));
        $response->assertSessionHas('status', 'Backup record deleted.');
        $this->assertDatabaseMissing('system_backups', ['id' => $backup->id]);
        $this->assertFalse(Storage::disk('backups')->exists($backup->archive_path));
    }

    #[Test]
    public function deleting_completed_backup_is_blocked(): void
    {
        Storage::fake('backups');

        $user = User::factory()->superAdmin()->create();
        $backup = SystemBackup::query()->create([
            'type' => SystemBackup::TYPE_MANUAL,
            'status' => SystemBackup::STATUS_COMPLETED,
            'includes_database' => true,
            'includes_uploads' => true,
            'archive_disk' => 'backups',
            'archive_path' => '2026/04/20/completed.zip',
            'archive_filename' => 'completed.zip',
            'started_at' => now(),
            'finished_at' => now(),
            'summary' => 'Completed.',
        ]);

        Storage::disk('backups')->put($backup->archive_path, 'placeholder');

        $response = $this->actingAs($user)->delete(route('admin.system.backups.destroy', $backup));

        $response->assertRedirect(route('admin.system.backups.index'));
        $response->assertSessionHasErrors(['system_backup' => 'Only failed or running backups can be deleted.']);
        $this->assertDatabaseHas('system_backups', ['id' => $backup->id]);
        $this->assertTrue(Storage::disk('backups')->exists($backup->archive_path));
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
}
