<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\SystemBackups\CreateManagedBackup;
use WebBlocks\Cms\Actions\SystemBackups\StartAdminBackup;
use WebBlocks\Cms\Http\Controllers\Admin\SystemBackupController;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Models\SystemBackupRestore;
use WebBlocks\Cms\Support\Media\MediaTransformService;
use WebBlocks\Cms\Support\System\AdminBackupLauncher;
use WebBlocks\Cms\Support\System\BackupArchiveValidation;
use WebBlocks\Cms\Support\System\BackupOperations;
use WebBlocks\Cms\Support\System\BackupRestoreArchiveInspector;
use WebBlocks\Cms\Support\System\BackupRetentionSchema;
use WebBlocks\Cms\Support\System\MaintenanceCleanupState;
use WebBlocks\Cms\Support\System\SystemBackupArchiveResolver;
use WebBlocks\Cms\Support\System\SystemBackupManager;
use WebBlocks\Cms\Support\System\SystemBackupRestoreManager;
use WebBlocks\Cms\Tests\TestCase;
use ZipArchive;

/** Real SQL export/import, ZIP extraction, file replacement and restore maintenance. */
class StorageBackupRestoreTest extends TestCase
{
  protected bool $cacheFreshDatabaseSchema = false;

  private string $root;

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    // Optional local integration profile: an isolated socket-only fixture server.
    $socket = getenv('WEBBLOCKS_STORAGE_TEST_SOCKET');
    if (is_string($socket) && $socket !== '') {
      $app['config']->set('database.default', 'mysql');
      $app['config']->set('database.connections.mysql', [
        'driver' => 'mysql', 'unix_socket' => $socket,
        'database' => 'wbcms_storage_restore_test', 'username' => 'root', 'password' => '',
        'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
      ]);
    }
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->root = sys_get_temp_dir().'/wb-restore-'.bin2hex(random_bytes(8));
    $this->app->useStoragePath($this->root.'/storage');
    $this->app->usePublicPath($this->root.'/public');
    config()->set('filesystems.disks.public', ['driver' => 'local', 'root' => $this->root.'/uploads', 'url' => '/storage', 'throw' => true]);
    config()->set('filesystems.disks.backups', ['driver' => 'local', 'root' => $this->root.'/backups', 'throw' => true]);
    config()->set('filesystems.links', [$this->root.'/public/storage' => $this->root.'/uploads']);
    config()->set('media_transforms.variants', ['content' => ['width' => 64, 'height' => null, 'fit' => 'contain', 'quality' => 84]]);
    Storage::forgetDisk('public');
    Storage::forgetDisk('backups');
    File::ensureDirectoryExists(public_path('site/northstar'));
  }

  protected function tearDown(): void
  {
    $this->travelBack();
    // Testbench rolls the entire fresh schema back in teardown. The isolated
    // native fixture drops cross-referenced tables rather than restoring data.
    if (getenv('WEBBLOCKS_STORAGE_TEST_SOCKET')) {
      Schema::disableForeignKeyConstraints();
    }
    File::deleteDirectory($this->root);
    parent::tearDown();
  }

  #[Test]
  public function lean_backup_restores_database_originals_and_site_files_then_rebuilds_the_same_variant_url(): void
  {
    if (! extension_loaded('gd')) {
      $this->markTestSkipped('GD is optional; this round trip additionally verifies image regeneration.');
    }
    $this->travelTo(now()->startOfSecond());
    $site = Site::create(['name' => 'Northstar Studio', 'handle' => 'northstar', 'is_primary' => true]);
    $image = imagecreatetruecolor(128, 80);
    imagefill($image, 0, 0, imagecolorallocate($image, 34, 120, 210));
    ob_start();
    imagepng($image);
    $original = ob_get_clean();
    imagedestroy($image);
    Storage::disk('public')->put('media/images/synthetic.png', $original);
    Storage::disk('public')->put('documents/synthetic.txt', 'Synthetic attachment');
    // Prefix lookalikes are ordinary uploads and must not be excluded.
    Storage::disk('public')->put('media/transforms-other/keep.txt', 'Keep this upload');
    File::put(public_path('site/northstar/site.css'), 'body { color: navy; }');
    $media = Media::create(['disk' => 'public', 'path' => 'media/images/synthetic.png', 'filename' => 'synthetic.png', 'original_name' => 'synthetic.png', 'kind' => 'image', 'extension' => 'png', 'mime_type' => 'image/png', 'size' => strlen($original), 'width' => 128, 'height' => 80, 'visibility' => 'public']);
    $variant = app(MediaTransformService::class)->result($media, 'content');
    $this->assertSame('generated', $variant->status);
    $variantFiles = Storage::disk('public')->allFiles('media/transforms');
    $this->assertCount(1, $variantFiles);
    $variantBytes = Storage::disk('public')->get($variantFiles[0]);
    Storage::disk('public')->put('media/transforms/obsolete/orphan.png', 'obsolete cache');

    $manager = app(SystemBackupManager::class);
    $backup = $manager->createManualBackup();
    $databaseStages = DB::connection()->getDriverName() === 'sqlite'
      ? ['SQLite SQL export']
      : ['MySQL dump', 'Raw SQL validation'];
    foreach ([...$databaseStages, 'File scan and ZIP entry registration', 'ZIP writing', 'Final archive validation'] as $stage) {
      $this->assertMatchesRegularExpression('/^'.preg_quote($stage, '/').': [0-9]+ ms$/m', $backup->output);
    }
    $source = Storage::disk('backups')->path($backup->archive_path);
    $sourceHash = hash_file('sha256', $source);
    $zip = new ZipArchive;
    $this->assertTrue($zip->open($source));
    try {
      $this->assertFalse($zip->locateName('uploads/public/'.$variantFiles[0]));
      $this->assertFalse($zip->locateName('uploads/public/media/transforms/obsolete/orphan.png'));
      $this->assertSame($original, $zip->getFromName('uploads/public/'.$media->path));
      $this->assertSame('Keep this upload', $zip->getFromName('uploads/public/media/transforms-other/keep.txt'));
      $this->assertSame(['media/transforms/'], json_decode($zip->getFromName('manifest.json'), true)['excluded_upload_paths']);
    } finally {
      $zip->close();
    }
    $inspection = app(BackupRestoreArchiveInspector::class)->inspect($source);
    $this->assertTrue($inspection->includesDatabase);
    $this->assertTrue($inspection->includesUploads);
    $this->assertTrue($inspection->includesSitePublic);

    $site->update(['name' => 'Changed after backup']);
    Storage::disk('public')->put($media->path, 'changed');
    Storage::disk('public')->put('after-backup.txt', 'remove on restore');
    File::put(public_path('site/northstar/site.css'), 'changed');
    $result = app(SystemBackupRestoreManager::class)->restoreFromBackup($backup);
    $this->assertSame($sourceHash, hash_file('sha256', $source), 'Safety backup must not overwrite the source archive in the same second.');
    $this->assertNotSame($backup->archive_path, $result->safetyBackup->archive_path);
    $this->assertFileExists(Storage::disk('backups')->path($result->safetyBackup->archive_path));
    $this->assertSame('Northstar Studio', Site::findOrFail($site->id)->name);
    $this->assertSame($original, Storage::disk('public')->get($media->path));
    $this->assertSame('Synthetic attachment', Storage::disk('public')->get('documents/synthetic.txt'));
    Storage::disk('public')->assertMissing('after-backup.txt');
    $this->assertSame('body { color: navy; }', File::get(public_path('site/northstar/site.css')));
    $this->assertSame([], Storage::disk('public')->allFiles('media/transforms'));
    $regenerated = app(MediaTransformService::class)->result(Media::findOrFail($media->id), 'content');
    $this->assertSame('generated', $regenerated->status);
    $this->assertSame($variant->url, $regenerated->url);
    $this->assertSame($variantBytes, Storage::disk('public')->get($variantFiles[0]));
    $this->assertSame('reused', app(MediaTransformService::class)->result($media->fresh(), 'content')->status);
    $this->assertSame(SystemBackupRestore::STATUS_COMPLETED, $result->restoreRecord->status);
    $this->assertSame($backup->id, $result->restoreRecord->source_backup_id);
    $this->assertSame(SystemBackup::STATUS_COMPLETED, $backup->fresh()->status);
    $this->assertSame($backup->output, $backup->fresh()->output);
    $this->assertSame(['database', 'uploads', 'site_public'], $result->inspection->restoredParts());
    $this->assertSame([], File::directories(storage_path('app/temp/system-backup-restores')));
    $this->assertTrue(is_link(public_path('storage')));
    $this->assertDatabaseIntegrity();
    // The pre-restore safety archive must itself recover the replaced state.
    $safety = app(SystemBackupRestoreManager::class)->restoreReference($result->safetyBackup->archive_path);
    $this->assertSame('Changed after backup', Site::findOrFail($site->id)->name);
    $this->assertSame('changed', Storage::disk('public')->get($media->path));
    $this->assertSame('remove on restore', Storage::disk('public')->get('after-backup.txt'));
    $this->assertSame('changed', File::get(public_path('site/northstar/site.css')));
    $this->assertSame(SystemBackupRestore::STATUS_COMPLETED, $safety->restoreRecord->status);
    $this->assertDatabaseIntegrity();

  }

  #[Test]
  public function a_failed_final_inspection_keeps_the_timings_in_the_failed_backup_log(): void
  {
    $this->mock(BackupRestoreArchiveInspector::class)->shouldReceive('inspect')->once()
      ->andReturnUsing(function (string $archivePath): never {
        $backup = SystemBackup::query()->sole();
        $this->assertSame(SystemBackup::STATUS_RUNNING, $backup->status);
        $this->assertNull($backup->finished_at);
        $this->assertFileExists($archivePath);
        $this->assertFalse(app(SystemBackupManager::class)->archiveResolution($backup)->isAvailable());

        throw new \RuntimeException('Synthetic validation failure.');
      });
    Schema::dropIfExists(BackupArchiveValidation::TABLE);

    try {
      app(SystemBackupManager::class)->createPreUpdateBackup();
      $this->fail('The backup should fail when its final inspection fails.');
    } catch (\RuntimeException $exception) {
      $this->assertSame('Synthetic validation failure.', $exception->getMessage());
    }

    $backup = SystemBackup::query()->sole();
    $this->assertSame(SystemBackup::STATUS_FAILED, $backup->status);
    $this->assertMatchesRegularExpression('/^ZIP writing: [0-9]+ ms$/m', $backup->output);
    $this->assertMatchesRegularExpression('/^Final archive validation: [0-9]+ ms$/m', $backup->output);
    $this->assertStringContainsString('Backup failed: Synthetic validation failure.', $backup->output);
    Storage::disk('backups')->assertMissing($backup->archive_path);
  }

  #[Test]
  public function a_panel_backup_is_admitted_before_the_worker_and_progress_only_reads_the_record(): void
  {
    $actorId = DB::table('users')->insertGetId([
      'name' => 'Backup operator', 'email' => 'backup@example.test', 'password' => 'fixture',
    ]);
    $this->mock(AdminBackupLauncher::class)->shouldReceive('launch')->once()
      ->andReturnUsing(function (int $id, string $filename): void {
        $pending = SystemBackup::findOrFail($id);
        $this->assertEquals($filename, $pending->archive_filename);
        $this->assertTrue($pending->isRunning());
        $this->assertNull($pending->started_at);
        $this->assertFalse(app(BackupOperations::class)->isBusy());
        Storage::disk('backups')->assertMissing($filename);
      });

    $backup = app(StartAdminBackup::class)->execute(['is_protected' => true, 'retention_mode' => 'never'], $actorId);
    $controller = app(SystemBackupController::class);
    $this->assertSame('running', $controller->progress($backup)->getData()->state);
    $this->assertNull($backup->fresh()->started_at);
    $this->assertTrue($backup->is_protected);
    $this->assertSame($actorId, $backup->triggered_by_user_id);

    $this->artisan('system:backups:work', ['backup' => $backup->id, 'filename' => $backup->archive_filename])->assertSuccessful();
    $this->assertSame('completed', $controller->progress($backup->fresh())->getData()->state);
    $this->assertSame('valid', app(BackupArchiveValidation::class)->records([$backup->id])->first()->status);
    $this->artisan('system:backups:work', ['backup' => $backup->id, 'filename' => $backup->archive_filename])->assertFailed();
    $this->assertSame(1, SystemBackup::count());
  }

  #[Test]
  public function abandoned_backups_are_reconciled_under_the_lease_and_valid_legacy_records_are_kept(): void
  {
    $manager = app(SystemBackupManager::class);
    $valid = $manager->createManualBackup();
    $marker = 'Backup record marked as completed before database snapshot.';
    $valid->update(['output' => $marker, 'archive_size_bytes' => null]);
    $legacy = SystemBackup::create([
      'type' => SystemBackup::TYPE_MANUAL, 'status' => SystemBackup::STATUS_COMPLETED,
      'archive_disk' => 'backups', 'archive_path' => 'missing.zip', 'archive_filename' => 'missing.zip',
      'output' => $marker, 'finished_at' => now(),
    ]);
    $pending = SystemBackup::create(['type' => SystemBackup::TYPE_MANUAL, 'status' => SystemBackup::STATUS_RUNNING]);
    $this->travel(2)->minutes();

    app(BackupOperations::class)->run(function () use ($manager, $pending): void {
      $manager->markStaleBackupsAsFailed();
      $this->assertTrue($pending->fresh()->isRunning());
    });
    $this->assertSame('interrupted', app(SystemBackupController::class)->progress($pending)->getData()->state);
    $this->assertTrue($pending->fresh()->isRunning());
    $manager->markStaleBackupsAsFailed();
    $this->assertTrue($valid->fresh()->isSuccessful());
    $this->assertTrue($legacy->fresh()->isFailed());
    $this->assertTrue($pending->fresh()->isFailed());
  }

  #[Test]
  public function legacy_archives_with_cached_variants_still_restore_without_new_manifest_metadata(): void
  {
    Storage::disk('public')->put('media/images/old.jpg', 'legacy original');
    $backup = app(SystemBackupManager::class)->createManualBackup();
    $archivePath = Storage::disk('backups')->path($backup->archive_path);
    $zip = new ZipArchive;
    $zip->open($archivePath);
    $manifest = json_decode($zip->getFromName('manifest.json'), true);
    unset($manifest['excluded_upload_paths'], $manifest['product'], $manifest['package_type'], $manifest['format_version'], $manifest['feature_version']);
    $zip->addFromString('manifest.json', json_encode($manifest));
    $zip->addFromString('uploads/public/media/transforms/1/old/content.jpg', 'legacy cached variant');
    $zip->close();
    Storage::disk('public')->delete('media/images/old.jpg');
    $result = app(SystemBackupRestoreManager::class)->restoreFromBackup($backup);
    $this->assertSame('legacy original', Storage::disk('public')->get('media/images/old.jpg'));
    $this->assertSame('legacy cached variant', Storage::disk('public')->get('media/transforms/1/old/content.jpg'));
    $this->assertSame(SystemBackupRestore::STATUS_COMPLETED, $result->restoreRecord->status);
  }

  #[Test]
  public function canonical_disk_root_handling_still_rejects_traversal_and_external_symlinks(): void
  {
    $backup = app(SystemBackupManager::class)->createManualBackup();
    $resolver = app(SystemBackupArchiveResolver::class);
    $this->assertTrue($resolver->resolvePath($backup->archive_path)->isAvailable());
    $this->assertTrue($resolver->resolvePath(Storage::disk('backups')->path($backup->archive_path))->isAvailable());
    File::put($this->root.'/outside.zip', 'outside');
    symlink($this->root.'/outside.zip', $this->root.'/backups/escape.zip');
    $this->assertTrue($resolver->resolvePath('escape.zip')->isUnsafe());
    $this->assertTrue($resolver->resolvePath('../outside.zip')->isUnsafe());
    $this->assertTrue($resolver->resolvePath($this->root.'/outside.zip')->isUnsafe());
  }

  #[Test]
  public function a_snapshot_before_retention_columns_restores_and_repairs_the_additive_schema(): void
  {
    $site = Site::create(['name' => 'Before retention', 'handle' => 'northstar', 'is_primary' => true]);
    Storage::disk('public')->put('documents/synthetic.txt', 'Legacy content');
    foreach (['is_protected', 'retention_mode', 'expires_at'] as $column) {
      Schema::table('wbcms_system_backups', fn ($table) => $table->dropColumn($column));
    }
    Schema::dropIfExists(MaintenanceCleanupState::TABLE);
    $backup = app(SystemBackupManager::class)->createManualBackup();
    BackupRetentionSchema::migrate();
    MaintenanceCleanupState::migrate();
    $site->update(['name' => 'After retention']);
    Storage::disk('public')->put('documents/synthetic.txt', 'Changed content');
    $result = app(SystemBackupRestoreManager::class)->restoreFromBackup($backup);
    $this->assertSame('Before retention', Site::findOrFail($site->id)->name);
    $this->assertSame('Legacy content', Storage::disk('public')->get('documents/synthetic.txt'));
    $this->assertTrue(BackupRetentionSchema::ready());
    $this->assertTrue(app(MaintenanceCleanupState::class)->ready());
    $this->assertSame(SystemBackupRestore::STATUS_COMPLETED, $result->restoreRecord->status);
    $this->assertDatabaseIntegrity();
    app(SystemBackupRestoreManager::class)->restoreReference($result->safetyBackup->archive_path);
    $this->assertSame('After retention', Site::findOrFail($site->id)->name);
    $this->assertSame('Changed content', Storage::disk('public')->get('documents/synthetic.txt'));
    $this->assertTrue(BackupRetentionSchema::ready());
    $this->assertDatabaseIntegrity();
  }

  #[Test]
  public function creation_protection_is_inside_the_database_snapshot_and_survives_restoration(): void
  {
    $backup = app(CreateManagedBackup::class)->execute(['is_protected' => true, 'retention_mode' => 'never']);
    $this->assertTrue($backup->is_protected);
    $backup->update(['is_protected' => false, 'retention_mode' => 'policy']);
    app(SystemBackupRestoreManager::class)->restoreFromBackup($backup);
    $restored = $backup->fresh();
    $this->assertTrue($restored->is_protected);
    $this->assertSame('never', $restored->retention_mode);
    $this->assertDatabaseIntegrity();
  }

  private function assertDatabaseIntegrity(): void
  {
    if (DB::connection()->getDriverName() === 'sqlite') {
      $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    } else {
      $this->assertSame(1, (int) DB::selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled);
    }
  }
}
