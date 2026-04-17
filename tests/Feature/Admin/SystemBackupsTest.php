<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\SystemBackup;
use App\Models\User;
use App\Support\System\DatabaseDumpWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class SystemBackupsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_view_backups_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.system.backups.index'));

        $response->assertOk();
        $response->assertSee('Backups');
        $response->assertSee('Create backup');
    }

    #[Test]
    public function backups_page_still_loads_when_backup_table_is_missing(): void
    {
        Schema::drop('system_backups');

        $user = User::factory()->create();

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

        $user = User::factory()->create();
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

        $user = User::factory()->create();
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

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
