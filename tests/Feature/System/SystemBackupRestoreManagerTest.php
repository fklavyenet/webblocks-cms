<?php

namespace Tests\Feature\System;

use App\Models\SystemBackup;
use App\Models\SystemBackupRestore;
use App\Models\User;
use App\Support\System\DatabaseRestoreRunner;
use App\Support\System\SystemBackupManager;
use App\Support\System\SystemBackupRestoreMaintenanceRunner;
use App\Support\System\SystemBackupRestoreManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class SystemBackupRestoreManagerTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryDirectories = [];

    #[Test]
    public function restore_attempts_safety_backup_restores_db_and_uploads_and_keeps_source_archive(): void
    {
        Storage::fake('backups');

        $user = User::factory()->create();
        $publicRoot = $this->makeTemporaryDirectory('restore-public-root');
        config()->set('filesystems.disks.public.root', $publicRoot);

        File::put($publicRoot.'/old.txt', 'old');

        $sourceBackup = $this->createBackupRecord('2026/04/20/source-backup.zip');
        $safetyBackup = $this->createBackupRecord('2026/04/20/safety-backup.zip', SystemBackup::TYPE_RESTORE_SAFETY);

        $this->createArchive($sourceBackup->archive_path, [
            'manifest.json' => json_encode([
                'included_parts' => [
                    'database' => true,
                    'uploads' => true,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'database/database.sql' => 'select 1;',
            'uploads/public/media/restored.txt' => 'restored file',
        ]);

        $fakeBackupManager = new FakeSystemBackupManager($safetyBackup);
        $fakeDatabaseRestoreRunner = new FakeDatabaseRestoreRunner;
        $fakeMaintenanceRunner = new FakeRestoreMaintenanceRunner;

        $this->app->instance(SystemBackupManager::class, $fakeBackupManager);
        $this->app->instance(DatabaseRestoreRunner::class, $fakeDatabaseRestoreRunner);
        $this->app->instance(SystemBackupRestoreMaintenanceRunner::class, $fakeMaintenanceRunner);

        $result = app(SystemBackupRestoreManager::class)->restoreFromBackup($sourceBackup, $user->id);

        $this->assertSame(1, $fakeBackupManager->validateCalls);
        $this->assertSame(1, $fakeBackupManager->safetyBackupCalls);
        $this->assertCount(1, $fakeDatabaseRestoreRunner->restoredSqlPaths);
        $this->assertStringEndsWith('database/database.sql', $fakeDatabaseRestoreRunner->restoredSqlPaths[0]);
        $this->assertSame(1, $fakeMaintenanceRunner->runCalls);
        $this->assertSame(['database', 'uploads'], $result->inspection->restoredParts());
        $this->assertSame($safetyBackup->id, $result->safetyBackup?->id);
        $this->assertFileExists($publicRoot.'/media/restored.txt');
        $this->assertFileDoesNotExist($publicRoot.'/old.txt');
        $this->assertTrue(Storage::disk('backups')->exists($sourceBackup->archive_path));

        $restoreRecord = SystemBackupRestore::query()->latest()->first();

        $this->assertNotNull($restoreRecord);
        $this->assertSame(SystemBackupRestore::STATUS_COMPLETED, $restoreRecord->status);
        $this->assertSame($sourceBackup->id, $restoreRecord->source_backup_id);
        $this->assertSame($safetyBackup->id, $restoreRecord->safety_backup_id);
    }

    #[Test]
    public function invalid_archive_aborts_cleanly_before_restore_and_records_failure(): void
    {
        Storage::fake('backups');

        $user = User::factory()->create();
        config()->set('filesystems.disks.public.root', $this->makeTemporaryDirectory('invalid-restore-public-root'));

        $sourceBackup = $this->createBackupRecord('2026/04/20/invalid-backup.zip');

        $this->createArchive($sourceBackup->archive_path, [
            'database/database.sql' => 'select 1;',
        ]);

        $fakeBackupManager = new FakeSystemBackupManager;
        $fakeDatabaseRestoreRunner = new FakeDatabaseRestoreRunner;
        $fakeMaintenanceRunner = new FakeRestoreMaintenanceRunner;

        $this->app->instance(SystemBackupManager::class, $fakeBackupManager);
        $this->app->instance(DatabaseRestoreRunner::class, $fakeDatabaseRestoreRunner);
        $this->app->instance(SystemBackupRestoreMaintenanceRunner::class, $fakeMaintenanceRunner);

        try {
            app(SystemBackupRestoreManager::class)->restoreFromBackup($sourceBackup, $user->id);
            $this->fail('Expected restore to fail for an invalid archive.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Backup archive is missing manifest.json.', $exception->getMessage());
        }

        $this->assertSame(1, $fakeBackupManager->validateCalls);
        $this->assertSame(0, $fakeBackupManager->safetyBackupCalls);
        $this->assertCount(0, $fakeDatabaseRestoreRunner->restoredSqlPaths);
        $this->assertSame(0, $fakeMaintenanceRunner->runCalls);
        $this->assertTrue(Storage::disk('backups')->exists($sourceBackup->archive_path));

        $restoreRecord = SystemBackupRestore::query()->latest()->first();

        $this->assertNotNull($restoreRecord);
        $this->assertSame(SystemBackupRestore::STATUS_FAILED, $restoreRecord->status);
        $this->assertSame($sourceBackup->id, $restoreRecord->source_backup_id);
        $this->assertNull($restoreRecord->safety_backup_id);
        $this->assertSame('Backup archive is missing manifest.json.', $restoreRecord->error_message);
    }

    private function createBackupRecord(string $archivePath, string $type = SystemBackup::TYPE_MANUAL): SystemBackup
    {
        return SystemBackup::query()->create([
            'type' => $type,
            'status' => SystemBackup::STATUS_COMPLETED,
            'includes_database' => true,
            'includes_uploads' => true,
            'archive_disk' => 'backups',
            'archive_path' => $archivePath,
            'archive_filename' => basename($archivePath),
            'started_at' => now(),
            'finished_at' => now(),
            'summary' => 'Completed.',
        ]);
    }

    private function createArchive(string $relativePath, array $entries): void
    {
        $archivePath = Storage::disk('backups')->path($relativePath);
        File::ensureDirectoryExists(dirname($archivePath));

        $archive = new ZipArchive;
        $this->assertTrue($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);

        foreach ($entries as $path => $contents) {
            $archive->addFromString($path, $contents);
        }

        $archive->close();
    }

    private function makeTemporaryDirectory(string $prefix): string
    {
        $path = storage_path('app/testing-system-backup-restore/'.$prefix.'-'.Str::uuid());
        File::ensureDirectoryExists($path);
        $this->temporaryDirectories[] = $path;

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            File::deleteDirectory($directory);
        }

        parent::tearDown();
    }
}

class FakeSystemBackupManager extends SystemBackupManager
{
    public int $safetyBackupCalls = 0;

    public int $validateCalls = 0;

    public function __construct(
        private readonly ?SystemBackup $safetyBackup = null,
    ) {}

    public function createRestoreSafetyBackup(?int $triggeredByUserId = null, ?string $label = null): SystemBackup
    {
        $this->safetyBackupCalls++;

        if (! $this->safetyBackup instanceof SystemBackup) {
            throw new RuntimeException('Safety backup was not configured for this fake manager.');
        }

        return $this->safetyBackup;
    }

    public function assertValidArchiveRelativePath(string $path): void
    {
        $this->validateCalls++;

        if (str_contains($path, '..')) {
            throw new RuntimeException('Backup archive path is invalid.');
        }
    }
}

class FakeDatabaseRestoreRunner extends DatabaseRestoreRunner
{
    public array $restoredSqlPaths = [];

    public function __construct() {}

    public function restoreFrom(string $sqlPath, array &$output = []): array
    {
        $this->restoredSqlPaths[] = $sqlPath;
        $output[] = 'Simulated database restore from '.basename($sqlPath).'.';

        return [
            'driver' => 'sqlite',
            'strategy' => 'fake',
            'connection' => 'testing',
        ];
    }
}

class FakeRestoreMaintenanceRunner extends SystemBackupRestoreMaintenanceRunner
{
    public int $runCalls = 0;

    public function run(array &$output = []): void
    {
        $this->runCalls++;
        $output[] = 'Simulated restore maintenance.';
    }
}
