<?php

namespace Tests\Unit\System;

use App\Support\System\BackupRestoreArchiveInspector;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class BackupRestoreArchiveInspectorTest extends TestCase
{
    private array $temporaryDirectories = [];

    #[Test]
    public function validation_fails_when_manifest_is_missing(): void
    {
        $archivePath = $this->makeArchive([
            'database/database.sql' => 'select 1;',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Backup archive is missing manifest.json.');

        app(BackupRestoreArchiveInspector::class)->inspect($archivePath);
    }

    #[Test]
    public function validation_fails_when_manifest_claims_uploads_without_uploads_directory(): void
    {
        $archivePath = $this->makeArchive([
            'manifest.json' => json_encode([
                'included_parts' => [
                    'database' => true,
                    'uploads' => true,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'database/database.sql' => 'select 1;',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Backup archive manifest says uploads are included, but uploads/public is missing.');

        app(BackupRestoreArchiveInspector::class)->inspect($archivePath);
    }

    #[Test]
    public function valid_archive_reports_database_and_upload_restore_steps(): void
    {
        $archivePath = $this->makeArchive([
            'manifest.json' => json_encode([
                'included_parts' => [
                    'database' => true,
                    'uploads' => true,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'database/database.sql' => 'select 1;',
            'uploads/public/media/example.txt' => 'restored',
        ]);

        $inspection = app(BackupRestoreArchiveInspector::class)->inspect($archivePath);

        $this->assertTrue($inspection->includesDatabase);
        $this->assertTrue($inspection->includesUploads);
        $this->assertSame(['database', 'uploads'], $inspection->restoredParts());
        $this->assertSame('database/database.sql', $inspection->databaseSqlPath);
        $this->assertSame('uploads/public', $inspection->uploadsRootPath);
    }

    private function makeArchive(array $entries): string
    {
        $directory = $this->makeTemporaryDirectory('backup-restore-archive');
        $archivePath = $directory.'/backup.zip';
        $archive = new ZipArchive;

        $this->assertTrue($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);

        foreach ($entries as $path => $contents) {
            $archive->addFromString($path, $contents);
        }

        $archive->close();

        return $archivePath;
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
