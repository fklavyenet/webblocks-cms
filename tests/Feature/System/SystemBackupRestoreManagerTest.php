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

        $sourceBackup = $this->createBackupRecord('source-backup.zip');
        $safetyBackup = $this->createBackupRecord('safety-backup.zip', SystemBackup::TYPE_RESTORE_SAFETY);

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

        $sourceBackup = $this->createBackupRecord('invalid-backup.zip');

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

    #[Test]
    public function invalid_sql_dump_aborts_before_safety_backup_and_database_restore(): void
    {
        Storage::fake('backups');

        $user = User::factory()->create();
        config()->set('filesystems.disks.public.root', $this->makeTemporaryDirectory('invalid-sql-restore-public-root'));

        $sourceBackup = $this->createBackupRecord('invalid-sql-backup.zip');

        $this->createArchive($sourceBackup->archive_path, [
            'manifest.json' => json_encode([
                'included_parts' => [
                    'database' => true,
                    'uploads' => false,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'database/database.sql' => "You executed `ddev exec --raw -- mysqldump --single-transaction demo`\n",
        ]);

        $fakeBackupManager = new FakeSystemBackupManager;
        $fakeDatabaseRestoreRunner = new FakeDatabaseRestoreRunner;
        $fakeMaintenanceRunner = new FakeRestoreMaintenanceRunner;

        $this->app->instance(SystemBackupManager::class, $fakeBackupManager);
        $this->app->instance(DatabaseRestoreRunner::class, $fakeDatabaseRestoreRunner);
        $this->app->instance(SystemBackupRestoreMaintenanceRunner::class, $fakeMaintenanceRunner);

        try {
            app(SystemBackupRestoreManager::class)->restoreFromBackup($sourceBackup, $user->id);
            $this->fail('Expected restore to fail for an invalid SQL dump.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Backup archive database/database.sql contains command output instead of SQL.', $exception->getMessage());
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
        $this->assertSame('Backup archive database/database.sql contains command output instead of SQL.', $restoreRecord->error_message);
    }

    #[Test]
    public function completed_restore_keeps_succeeding_when_source_backup_record_disappears_during_database_overwrite(): void
    {
        Storage::fake('backups');

        $user = User::factory()->create();
        $publicRoot = $this->makeTemporaryDirectory('restore-delete-source-public-root');
        config()->set('filesystems.disks.public.root', $publicRoot);

        $sourceBackup = $this->createBackupRecord('source-deleted-during-restore.zip');
        $safetyBackup = $this->createBackupRecord('safety-for-deleted-source.zip', SystemBackup::TYPE_RESTORE_SAFETY);

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
        $fakeDatabaseRestoreRunner = new class($sourceBackup) extends DatabaseRestoreRunner
        {
            public array $restoredSqlPaths = [];

            public function __construct(
                private readonly SystemBackup $sourceBackup,
            ) {}

            public function restoreFrom(string $sqlPath, array &$output = []): array
            {
                $this->restoredSqlPaths[] = $sqlPath;
                $this->sourceBackup->delete();
                $output[] = 'Simulated database restore from '.basename($sqlPath).' with source backup removal.';

                return [
                    'driver' => 'sqlite',
                    'strategy' => 'fake',
                    'connection' => 'testing',
                ];
            }
        };
        $fakeMaintenanceRunner = new FakeRestoreMaintenanceRunner;

        $this->app->instance(SystemBackupManager::class, $fakeBackupManager);
        $this->app->instance(DatabaseRestoreRunner::class, $fakeDatabaseRestoreRunner);
        $this->app->instance(SystemBackupRestoreMaintenanceRunner::class, $fakeMaintenanceRunner);

        $result = app(SystemBackupRestoreManager::class)->restoreFromBackup($sourceBackup, $user->id);

        $this->assertCount(1, $fakeDatabaseRestoreRunner->restoredSqlPaths);
        $this->assertSame(1, $fakeMaintenanceRunner->runCalls);
        $this->assertNotNull($result->restoreRecord);
        $this->assertNull($result->restoreRecord?->source_backup_id);
        $this->assertSame($safetyBackup->id, $result->restoreRecord?->safety_backup_id);
        $this->assertSame(SystemBackupRestore::STATUS_COMPLETED, $result->restoreRecord?->status);
        $this->assertDatabaseMissing('system_backups', ['id' => $sourceBackup->id]);
    }

    #[Test]
    public function restoring_existing_backup_uses_existing_archive_and_only_creates_the_expected_safety_backup(): void
    {
        Storage::fake('backups');

        $user = User::factory()->create();
        $publicRoot = $this->makeTemporaryDirectory('restore-existing-source-public-root');
        config()->set('filesystems.disks.public.root', $publicRoot);

        $sourceBackup = $this->createBackupRecord('source-existing.zip');

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

        $fakeBackupManager = new RecordingSafetyBackupManager;
        $fakeDatabaseRestoreRunner = new FakeDatabaseRestoreRunner;
        $fakeMaintenanceRunner = new FakeRestoreMaintenanceRunner;

        $this->app->instance(SystemBackupManager::class, $fakeBackupManager);
        $this->app->instance(DatabaseRestoreRunner::class, $fakeDatabaseRestoreRunner);
        $this->app->instance(SystemBackupRestoreMaintenanceRunner::class, $fakeMaintenanceRunner);

        $result = app(SystemBackupRestoreManager::class)->restoreFromBackup($sourceBackup, $user->id);

        $this->assertSame(1, $fakeBackupManager->safetyBackupCalls);
        $this->assertSame(1, $fakeBackupManager->validateCalls);
        $this->assertNotNull($fakeBackupManager->createdSafetyBackup);
        $this->assertSame(SystemBackup::TYPE_RESTORE_SAFETY, $fakeBackupManager->createdSafetyBackup?->type);
        $this->assertTrue(Storage::disk('backups')->exists((string) $sourceBackup->archive_path));
        $this->assertTrue(Storage::disk('backups')->exists((string) $fakeBackupManager->createdSafetyBackup?->archive_path));
        $this->assertSame(2, SystemBackup::query()->count());
        $this->assertSame(1, SystemBackup::query()->where('archive_path', $sourceBackup->archive_path)->count());
        $this->assertSame((string) $sourceBackup->archive_path, $result->sourceArchivePath);
        $this->assertSame($fakeBackupManager->createdSafetyBackup?->id, $result->safetyBackup?->id);
    }

    #[Test]
    public function restore_creates_a_completed_safety_backup_before_database_restore_begins(): void
    {
        $this->useRealBackupsDiskRoot('restore-safety-before-db');

        $user = User::factory()->create();
        $publicRoot = $this->makeTemporaryDirectory('restore-safety-before-db-public-root');
        config()->set('filesystems.disks.public.root', $publicRoot);
        File::put($publicRoot.'/original.txt', 'before restore');

        $sourceBackup = app(SystemBackupManager::class)->createManualBackup($user->id, 'Source backup');
        $testCase = $this;
        $fakeDatabaseRestoreRunner = new class($testCase) extends DatabaseRestoreRunner
        {
            public array $restoredSqlPaths = [];

            public function __construct(
                private readonly TestCase $testCase,
            ) {}

            public function restoreFrom(string $sqlPath, array &$output = []): array
            {
                $this->restoredSqlPaths[] = $sqlPath;

                $runningCount = SystemBackup::query()
                    ->where('status', SystemBackup::STATUS_RUNNING)
                    ->count();
                $safetyBackup = SystemBackup::query()
                    ->where('type', SystemBackup::TYPE_RESTORE_SAFETY)
                    ->latest()
                    ->first();

                $this->testCase->assertSame(0, $runningCount);
                $this->testCase->assertNotNull($safetyBackup);
                $this->testCase->assertSame(SystemBackup::STATUS_COMPLETED, $safetyBackup->status);
                $this->testCase->assertNotNull($safetyBackup->finished_at);
                $this->testCase->assertNotNull($safetyBackup->archive_path);
                $this->testCase->assertNotNull($safetyBackup->archive_filename);
                $this->testCase->assertSame(SystemBackupManager::ARCHIVE_DISK, $safetyBackup->archive_disk);
                $this->testCase->assertNotNull($safetyBackup->output);
                $this->testCase->assertTrue(Storage::disk(SystemBackupManager::ARCHIVE_DISK)->exists($safetyBackup->archive_path));

                $output[] = 'Simulated database restore from '.basename($sqlPath).'.';

                return [
                    'driver' => 'sqlite',
                    'strategy' => 'fake',
                    'connection' => 'testing',
                ];
            }
        };
        $fakeMaintenanceRunner = new FakeRestoreMaintenanceRunner;

        $this->app->instance(DatabaseRestoreRunner::class, $fakeDatabaseRestoreRunner);
        $this->app->instance(SystemBackupRestoreMaintenanceRunner::class, $fakeMaintenanceRunner);

        $result = app(SystemBackupRestoreManager::class)->restoreFromBackup($sourceBackup->fresh(), $user->id);

        $this->assertCount(1, $fakeDatabaseRestoreRunner->restoredSqlPaths);
        $this->assertSame(1, $fakeMaintenanceRunner->runCalls);
        $this->assertNotNull($result->safetyBackup);
        $this->assertSame(SystemBackup::STATUS_COMPLETED, $result->safetyBackup->status);
        $this->assertSame(0, SystemBackup::query()->where('status', SystemBackup::STATUS_RUNNING)->count());
    }

    #[Test]
    public function failing_safety_backup_aborts_restore_before_database_restore_and_records_failure(): void
    {
        Storage::fake('backups');

        $user = User::factory()->create();
        config()->set('filesystems.disks.public.root', $this->makeTemporaryDirectory('failed-safety-restore-public-root'));

        $sourceBackup = $this->createBackupRecord('failing-safety-source.zip');

        $this->createArchive($sourceBackup->archive_path, [
            'manifest.json' => json_encode([
                'included_parts' => [
                    'database' => true,
                    'uploads' => false,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'database/database.sql' => 'select 1;',
        ]);

        $fakeBackupManager = new class extends FakeSystemBackupManager
        {
            public function __construct() {}

            public function createRestoreSafetyBackup(?int $triggeredByUserId = null, ?string $label = null): SystemBackup
            {
                $this->safetyBackupCalls++;

                throw new RuntimeException('Pre-restore safety backup failed.');
            }
        };
        $fakeDatabaseRestoreRunner = new FakeDatabaseRestoreRunner;
        $fakeMaintenanceRunner = new FakeRestoreMaintenanceRunner;

        $this->app->instance(SystemBackupManager::class, $fakeBackupManager);
        $this->app->instance(DatabaseRestoreRunner::class, $fakeDatabaseRestoreRunner);
        $this->app->instance(SystemBackupRestoreMaintenanceRunner::class, $fakeMaintenanceRunner);

        try {
            app(SystemBackupRestoreManager::class)->restoreFromBackup($sourceBackup, $user->id);
            $this->fail('Expected restore to fail when the safety backup cannot be created.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Pre-restore safety backup failed.', $exception->getMessage());
        }

        $this->assertSame(1, $fakeBackupManager->validateCalls);
        $this->assertSame(1, $fakeBackupManager->safetyBackupCalls);
        $this->assertCount(0, $fakeDatabaseRestoreRunner->restoredSqlPaths);
        $this->assertSame(0, $fakeMaintenanceRunner->runCalls);

        $restoreRecord = SystemBackupRestore::query()->latest()->first();

        $this->assertNotNull($restoreRecord);
        $this->assertSame(SystemBackupRestore::STATUS_FAILED, $restoreRecord->status);
        $this->assertSame($sourceBackup->id, $restoreRecord->source_backup_id);
        $this->assertNull($restoreRecord->safety_backup_id);
        $this->assertSame('Pre-restore safety backup failed.', $restoreRecord->error_message);
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

    private function useRealBackupsDiskRoot(string $prefix): string
    {
        $path = $this->makeTemporaryDirectory($prefix);
        config()->set('filesystems.disks.backups.root', $path);

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

class RecordingSafetyBackupManager extends SystemBackupManager
{
    public int $safetyBackupCalls = 0;

    public int $validateCalls = 0;

    public ?SystemBackup $createdSafetyBackup = null;

    public function __construct() {}

    public function createRestoreSafetyBackup(?int $triggeredByUserId = null, ?string $label = null): SystemBackup
    {
        $this->safetyBackupCalls++;

        $archivePath = 'generated-safety-'.$this->safetyBackupCalls.'.zip';
        Storage::disk(SystemBackupManager::ARCHIVE_DISK)->put($archivePath, 'safety archive');

        return $this->createdSafetyBackup = SystemBackup::query()->create([
            'type' => SystemBackup::TYPE_RESTORE_SAFETY,
            'status' => SystemBackup::STATUS_COMPLETED,
            'label' => $label,
            'includes_database' => true,
            'includes_uploads' => true,
            'archive_disk' => SystemBackupManager::ARCHIVE_DISK,
            'archive_path' => $archivePath,
            'archive_filename' => basename($archivePath),
            'archive_size_bytes' => strlen('safety archive'),
            'started_at' => now(),
            'finished_at' => now(),
            'summary' => 'Completed.',
            'triggered_by_user_id' => $triggeredByUserId,
        ]);
    }

    public function assertValidArchiveRelativePath(string $path): void
    {
        $this->validateCalls++;

        if (str_contains($path, '..')) {
            throw new RuntimeException('Backup archive path is invalid.');
        }
    }
}
