<?php

namespace App\Support\System;

use App\Models\SystemBackup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UploadedSystemBackupManager
{
    public function __construct(
        private readonly BackupRestoreArchiveInspector $archiveInspector,
    ) {}

    public function import(UploadedFile $file, ?int $triggeredByUserId = null): SystemBackup
    {
        $startedAt = now();
        $sourceFilename = $file->getClientOriginalName();
        $safeFilename = Str::lower(Str::random(8)).'-webblocks-cms-backup-upload-'.$startedAt->format('Y-m-d-His').'.zip';
        $archivePath = $safeFilename;
        $disk = Storage::disk(SystemBackupManager::ARCHIVE_DISK);

        $storedPath = $disk->putFileAs('', $file, $archivePath);

        if (! is_string($storedPath)) {
            throw new RuntimeException('Backup archive could not be stored on the backups disk.');
        }

        try {
            $inspection = $this->archiveInspector->inspect($disk->path($storedPath));
            $archiveSize = $disk->size($storedPath);
            $manifest = $inspection->manifest;
            $finishedAt = now();
            $output = [
                'Backup archive uploaded and validated successfully.',
                'Source filename: '.$sourceFilename,
                'Archive validation passed.',
            ];

            return SystemBackup::query()->create([
                'type' => SystemBackup::TYPE_UPLOADED,
                'status' => SystemBackup::STATUS_COMPLETED,
                'label' => $sourceFilename,
                'includes_database' => $inspection->includesDatabase,
                'includes_uploads' => $inspection->includesUploads,
                'archive_disk' => SystemBackupManager::ARCHIVE_DISK,
                'archive_path' => $storedPath,
                'archive_filename' => basename($storedPath),
                'archive_size_bytes' => is_int($archiveSize) ? $archiveSize : null,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'duration_ms' => $startedAt->diffInMilliseconds($finishedAt),
                'summary' => 'Uploaded backup archive validated and registered for restore.',
                'output' => implode(PHP_EOL, array_merge($output, $this->manifestSummaryLines($manifest, $inspection))),
                'triggered_by_user_id' => $triggeredByUserId,
            ]);
        } catch (Throwable $throwable) {
            $disk->delete($storedPath);

            throw new RuntimeException($throwable->getMessage(), previous: $throwable);
        }
    }

    private function manifestSummaryLines(array $manifest, BackupRestoreInspection $inspection): array
    {
        return array_values(array_filter([
            'Manifest app: '.($manifest['app_name'] ?? 'unknown'),
            isset($manifest['app_version']) ? 'Manifest version: '.$manifest['app_version'] : null,
            isset($manifest['created_at']) ? 'Manifest created_at: '.$manifest['created_at'] : null,
            'Contents: '.implode(' + ', $inspection->restoredParts()),
        ]));
    }
}
