<?php

namespace App\Support\System;

use App\Models\SystemBackup;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
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

    public function normalizeArchivePathForDisk(string $storedPath, string $diskName = self::ARCHIVE_DISK): string
    {
        $trimmedPath = trim($storedPath);

        if ($trimmedPath === '') {
            throw new RuntimeException('Backup archive path is missing.');
        }

        if (str_contains($trimmedPath, '..')) {
            throw new RuntimeException('Backup archive path is invalid.');
        }

        $diskRoot = $this->resolvedDiskRoot($diskName);

        if ($diskRoot === null) {
            throw new RuntimeException('Backup archive root could not be resolved.');
        }

        $normalizedPath = $this->normalizePathSeparators($trimmedPath);

        foreach ($this->archivePathPrefixes($diskName, $diskRoot) as $prefix) {
            if ($prefix !== '' && str_starts_with($normalizedPath, $prefix)) {
                $normalizedPath = substr($normalizedPath, strlen($prefix));
                break;
            }
        }

        $normalizedPath = ltrim($normalizedPath, '/');

        if ($normalizedPath === '' || $this->hasInvalidRelativePath($normalizedPath)) {
            throw new RuntimeException('Backup archive path is invalid.');
        }

        $resolvedParent = $this->resolveArchiveParentDirectory($diskName, $normalizedPath);

        if ($resolvedParent !== null && ! str_starts_with($resolvedParent, $diskRoot.DIRECTORY_SEPARATOR) && $resolvedParent !== $diskRoot) {
            throw new RuntimeException('Backup archive path is outside the backups disk.');
        }

        return $normalizedPath;
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
        $archiveFilename = 'webblocks-cms-backup-'.$startedAt->format('Y-m-d-His').'.zip';
        $archiveRelativePath = $startedAt->format('Y/m/d').'/'.$archiveFilename;
        $archivePath = Storage::disk(self::ARCHIVE_DISK)->path($archiveRelativePath);
        $output = [];

        File::ensureDirectoryExists($temporaryDirectory);
        File::ensureDirectoryExists((string) config('filesystems.disks.public.root'));

        try {
            $this->markBackupCompletedForSnapshot(
                $backup,
                $archiveRelativePath,
                $archiveFilename,
                $startedAt,
                $output,
            );

            $databaseMeta = $this->databaseDumpWriter->dumpTo($databaseDumpPath, $output);
            $manifest = $this->backupManifestBuilder->build($backup, $databaseMeta, $archiveFilename);
            $archiveMeta = $this->backupArchiveBuilder->build($archivePath, $databaseDumpPath, $manifest, $output);
            $fileSize = filesize($archivePath);

            $this->finalizeCompletedBackup(
                $backup,
                $archiveRelativePath,
                $archiveFilename,
                $startedAt,
                $fileSize === false ? null : $fileSize,
                $archiveMeta,
                $output,
            );

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

    private function markBackupCompletedForSnapshot(
        SystemBackup $backup,
        string $archiveRelativePath,
        string $archiveFilename,
        $startedAt,
        array &$output,
    ): void {
        $finishedAt = now();
        $output[] = 'Backup record marked as completed before database snapshot.';

        $backup->forceFill([
            'status' => SystemBackup::STATUS_COMPLETED,
            'archive_path' => $archiveRelativePath,
            'archive_filename' => $archiveFilename,
            'archive_size_bytes' => null,
            'finished_at' => $finishedAt,
            'duration_ms' => $startedAt->diffInMilliseconds($finishedAt),
            'summary' => 'Backup completed.',
            'output' => implode(PHP_EOL, $output),
            'error_message' => null,
        ])->save();
    }

    private function finalizeCompletedBackup(
        SystemBackup $backup,
        string $archiveRelativePath,
        string $archiveFilename,
        $startedAt,
        ?int $archiveSizeBytes,
        array $archiveMeta,
        array $output,
    ): void {
        $finishedAt = now();

        $backup->forceFill([
            'status' => SystemBackup::STATUS_COMPLETED,
            'archive_path' => $archiveRelativePath,
            'archive_filename' => $archiveFilename,
            'archive_size_bytes' => $archiveSizeBytes,
            'finished_at' => $finishedAt,
            'duration_ms' => $startedAt->diffInMilliseconds($finishedAt),
            'summary' => 'Backup completed with database dump and '.number_format($archiveMeta['uploads_file_count']).' upload file(s).',
            'output' => implode(PHP_EOL, $output),
            'error_message' => null,
        ])->save();
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

    public function markStaleBackupsAsFailed(): void
    {
        if (! $this->hasBackupTable()) {
            return;
        }

        $backups = SystemBackup::query()
            ->where('status', SystemBackup::STATUS_RUNNING)
            ->whereNull('finished_at')
            ->get();

        foreach ($backups as $backup) {
            if (! $backup->isStaleRunning()) {
                continue;
            }

            $output = trim(implode(PHP_EOL.PHP_EOL, array_filter([
                $backup->output,
                'This backup did not finish in time and was marked as failed.',
            ])));

            $backup->update([
                'status' => SystemBackup::STATUS_FAILED,
                'summary' => 'This backup did not finish in time and was marked as failed.',
                'finished_at' => now(),
                'output' => $output,
                'error_message' => $backup->error_message ?: 'This backup did not finish in time and was marked as failed. You can delete this failed backup record or create a fresh backup.',
            ]);
        }
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

    public function deleteBackupRecord(SystemBackup $backup, bool $allowRunning = false): void
    {
        if ($backup->isRunning() && ! $backup->isStaleRunning() && ! $allowRunning) {
            throw new RuntimeException('Running backup cannot be deleted unless you explicitly confirm it is stuck.');
        }

        $archiveDisk = $this->resolveArchiveDiskName($backup->archive_disk);
        $storedArchivePath = $backup->archiveRelativePath();

        if ($storedArchivePath !== null) {
            $normalizedArchivePath = $this->normalizeArchivePathForDisk($storedArchivePath, $archiveDisk);
            $this->deleteArchiveIfPresent($backup, $archiveDisk, $storedArchivePath, $normalizedArchivePath);
        }

        $backup->delete();
    }

    private function resolveArchiveDiskName(?string $archiveDisk): string
    {
        return filled($archiveDisk) ? $archiveDisk : self::ARCHIVE_DISK;
    }

    private function deleteArchiveIfPresent(SystemBackup $backup, string $diskName, string $storedArchivePath, string $normalizedArchivePath): void
    {
        $disk = Storage::disk($diskName);
        $diskRoot = $this->resolvedDiskRoot($diskName);

        if (! $disk->exists($normalizedArchivePath)) {
            return;
        }

        if (! $disk->delete($normalizedArchivePath) || $disk->exists($normalizedArchivePath)) {
            Log::warning('Backup archive file could not be deleted.', [
                'backup_id' => $backup->id,
                'archive_disk' => $diskName,
                'stored_archive_path' => $storedArchivePath,
                'normalized_archive_path' => $normalizedArchivePath,
                'disk_root' => $diskRoot,
            ]);

            throw new RuntimeException('Backup archive file could not be deleted.');
        }

        $this->pruneEmptyArchiveDirectories($diskName, $normalizedArchivePath);
    }

    private function pruneEmptyArchiveDirectories(string $diskName, string $archivePath): void
    {
        $disk = Storage::disk($diskName);
        $root = realpath($disk->path(''));

        if ($root === false) {
            return;
        }

        $directory = dirname($archivePath);

        while ($directory !== '' && $directory !== '.' && $directory !== DIRECTORY_SEPARATOR) {
            $absoluteDirectory = realpath($disk->path($directory));

            if ($absoluteDirectory === false || ! str_starts_with($absoluteDirectory, $root.DIRECTORY_SEPARATOR)) {
                break;
            }

            if (! File::isDirectory($absoluteDirectory) || count(File::files($absoluteDirectory)) > 0 || count(File::directories($absoluteDirectory)) > 0) {
                break;
            }

            File::deleteDirectory($absoluteDirectory);
            $directory = dirname($directory);
        }
    }

    private function archivePathPrefixes(string $diskName, string $diskRoot): array
    {
        $diskPath = $this->normalizePathSeparators(Storage::disk($diskName)->path(''));
        $storageRoot = $this->normalizePathSeparators(storage_path('app'));

        return array_values(array_filter([
            rtrim($this->normalizePathSeparators($diskRoot), '/').'/',
            rtrim($diskPath, '/').'/',
            rtrim($storageRoot, '/').'/'.$diskName.'/',
            rtrim($storageRoot, '/').'/',
            $diskName.'/',
        ]));
    }

    private function resolvedDiskRoot(string $diskName): ?string
    {
        $root = realpath(Storage::disk($diskName)->path(''));

        return $root === false ? null : $root;
    }

    private function resolveArchiveParentDirectory(string $diskName, string $archivePath): ?string
    {
        $parent = dirname($archivePath);

        if ($parent === '' || $parent === '.') {
            return $this->resolvedDiskRoot($diskName);
        }

        $resolvedParent = realpath(Storage::disk($diskName)->path($parent));

        return $resolvedParent === false ? null : $resolvedParent;
    }

    private function normalizePathSeparators(string $path): string
    {
        return preg_replace('#/+#', '/', str_replace('\\', '/', $path)) ?? str_replace('\\', '/', $path);
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
