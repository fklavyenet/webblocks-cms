<?php

namespace App\Support\System;

use App\Models\SystemBackup;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SystemBackupManager
{
    public const ARCHIVE_DISK = 'backups';

    public const RECENT_BACKUP_HOURS = 24;

    public const TYPE_RESTORE_SAFETY = 'restore_safety';

    public function __construct(
        private readonly DatabaseDumpWriter $databaseDumpWriter,
        private readonly BackupManifestBuilder $backupManifestBuilder,
        private readonly BackupArchiveBuilder $backupArchiveBuilder,
    ) {}

    public function createManualBackup(?int $triggeredByUserId = null, ?string $label = null): SystemBackup
    {
        return $this->createBackup(SystemBackup::TYPE_MANUAL, $triggeredByUserId, $label);
    }

    public function createRestoreSafetyBackup(?int $triggeredByUserId = null, ?string $label = null): SystemBackup
    {
        return $this->createBackup(self::TYPE_RESTORE_SAFETY, $triggeredByUserId, $label);
    }

    public function createPreUpdateBackup(?int $triggeredByUserId = null, ?string $label = null): SystemBackup
    {
        return $this->createBackup(SystemBackup::TYPE_PRE_UPDATE, $triggeredByUserId, $label);
    }

    public function assertValidArchiveRelativePath(string $path): void
    {
        if ($this->hasInvalidRelativePath($path)) {
            throw new RuntimeException('Backup archive path is invalid.');
        }
    }

    private function createBackup(string $type, ?int $triggeredByUserId = null, ?string $label = null): SystemBackup
    {
        $startedAt = now();
        $backup = SystemBackup::query()->create([
            'type' => $type,
            'status' => SystemBackup::STATUS_RUNNING,
            'label' => $label,
            'includes_database' => true,
            'includes_uploads' => true,
            'archive_disk' => self::ARCHIVE_DISK,
            'started_at' => $startedAt,
            'triggered_by_user_id' => $triggeredByUserId,
        ]);

        $temporaryDirectory = storage_path('app/temp/system-backups/'.$startedAt->format('YmdHis').'-'.Str::lower(Str::random(8)));
        $databaseDumpPath = $temporaryDirectory.'/database.sql';
        $archiveRelativePath = null;
        $output = [];

        File::ensureDirectoryExists($temporaryDirectory);
        File::ensureDirectoryExists((string) config('filesystems.disks.public.root'));

        try {
            $databaseMeta = $this->databaseDumpWriter->dumpTo($databaseDumpPath, $output);

            $archiveFilename = 'webblocks-cms-backup-'.$startedAt->format('Y-m-d-His').'.zip';
            $archiveRelativePath = $startedAt->format('Y/m/d').'/'.$archiveFilename;
            $archivePath = Storage::disk(self::ARCHIVE_DISK)->path($archiveRelativePath);
            $manifest = $this->backupManifestBuilder->build($backup, $databaseMeta, $archiveFilename);
            $archiveMeta = $this->backupArchiveBuilder->build($archivePath, $databaseDumpPath, $manifest, $output);
            $finishedAt = now();
            $fileSize = filesize($archivePath);

            $backup->forceFill([
                'status' => SystemBackup::STATUS_COMPLETED,
                'archive_path' => $archiveRelativePath,
                'archive_filename' => $archiveFilename,
                'archive_size_bytes' => $fileSize === false ? null : $fileSize,
                'finished_at' => $finishedAt,
                'duration_ms' => $startedAt->diffInMilliseconds($finishedAt),
                'summary' => 'Backup completed with database dump and '.number_format($archiveMeta['uploads_file_count']).' upload file(s).',
                'output' => implode(PHP_EOL, $output),
                'error_message' => null,
            ])->save();

            return $backup->fresh();
        } catch (Throwable $throwable) {
            if ($archiveRelativePath !== null) {
                Storage::disk(self::ARCHIVE_DISK)->delete($archiveRelativePath);
            }

            $output[] = 'Backup failed: '.$throwable->getMessage();
            $finishedAt = now();

            $backup->forceFill([
                'status' => SystemBackup::STATUS_FAILED,
                'finished_at' => $finishedAt,
                'duration_ms' => $startedAt->diffInMilliseconds($finishedAt),
                'summary' => 'Backup failed.',
                'output' => implode(PHP_EOL, $output),
                'error_message' => $throwable->getMessage(),
            ])->save();

            throw new RuntimeException($throwable->getMessage(), previous: $throwable);
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }
    }

    public function latest(): ?SystemBackup
    {
        if (! $this->hasBackupTable()) {
            return null;
        }

        return SystemBackup::query()->with('triggeredBy')->latest()->first();
    }

    public function latestSuccessful(): ?SystemBackup
    {
        if (! $this->hasBackupTable()) {
            return null;
        }

        return SystemBackup::query()
            ->with('triggeredBy')
            ->where('status', SystemBackup::STATUS_COMPLETED)
            ->latest('finished_at')
            ->latest()
            ->first();
    }

    public function freshnessSummary(int $hours = self::RECENT_BACKUP_HOURS): array
    {
        $latest = $this->latest();
        $latestSuccessful = $this->latestSuccessful();
        $hasRecentSuccessfulBackup = $latestSuccessful?->isRecentSuccessful($hours) ?? false;

        return [
            'hours' => $hours,
            'latest' => $latest,
            'latest_successful' => $latestSuccessful,
            'has_recent_successful_backup' => $hasRecentSuccessfulBackup,
        ];
    }

    public function downloadResponse(SystemBackup $backup): BinaryFileResponse
    {
        if (! $backup->isSuccessful() || $backup->archive_path === null || $backup->archive_filename === null) {
            abort(404);
        }

        if ($this->hasInvalidRelativePath($backup->archive_path)) {
            abort(404);
        }

        $disk = Storage::disk($backup->archive_disk);

        if (! $disk->exists($backup->archive_path)) {
            abort(404);
        }

        $path = $disk->path($backup->archive_path);
        $root = realpath(dirname($disk->path('backup-root-probe')));
        $resolvedPath = realpath($path);

        if ($root === false || $resolvedPath === false) {
            abort(404);
        }

        if (! str_starts_with($resolvedPath, $root.DIRECTORY_SEPARATOR) && $resolvedPath !== $root) {
            abort(404);
        }

        return response()->download($resolvedPath, $backup->archive_filename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function deleteBackupRecord(SystemBackup $backup): void
    {
        if (! $backup->isDeletable()) {
            throw new RuntimeException('Only failed or running backups can be deleted.');
        }

        $archivePath = $backup->archiveRelativePath();

        if ($archivePath !== null) {
            $this->assertValidArchiveRelativePath($archivePath);
            $disk = Storage::disk($backup->archive_disk ?: self::ARCHIVE_DISK);

            if ($disk->exists($archivePath)) {
                $disk->delete($archivePath);
            }
        }

        $backup->delete();
    }

    private function hasBackupTable(): bool
    {
        return Schema::hasTable('system_backups');
    }

    private function hasInvalidRelativePath(string $path): bool
    {
        return str_contains($path, '..')
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:\\\\/', $path) === 1;
    }
}
