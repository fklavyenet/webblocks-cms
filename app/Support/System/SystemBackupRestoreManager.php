<?php

namespace App\Support\System;

use App\Models\SystemBackup;
use App\Models\SystemBackupRestore;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SystemBackupRestoreManager
{
    public function __construct(
        private readonly SystemBackupManager $systemBackupManager,
        private readonly BackupRestoreArchiveInspector $archiveInspector,
        private readonly BackupRestoreArchiveExtractor $archiveExtractor,
        private readonly DatabaseRestoreRunner $databaseRestoreRunner,
        private readonly SystemBackupRestoreMaintenanceRunner $maintenanceRunner,
    ) {}

    public function describeRestoreSource(string $reference): array
    {
        $target = $this->resolveRestoreSource($reference);
        $inspection = $this->archiveInspector->inspect(
            Storage::disk($target['archive_disk'])->path($target['archive_path'])
        );

        return $target + [
            'inspection' => $inspection,
            'backup_summary' => [
                'created_at' => (string) ($inspection->manifest['created_at'] ?? 'unknown'),
                'backup_id' => $inspection->manifest['backup_id'] ?? null,
                'backup_type' => $inspection->manifest['backup_type'] ?? null,
                'app_version' => $inspection->manifest['app_version'] ?? null,
                'restored_parts' => $inspection->restoredParts(),
                'source_backup_status' => $target['backup']?->status,
            ],
        ];
    }

    public function restoreReference(string $reference, ?int $triggeredByUserId = null): BackupRestoreResult
    {
        $target = $this->resolveRestoreSource($reference);

        return $this->restoreTarget($target, $triggeredByUserId);
    }

    public function restoreFromBackup(SystemBackup $backup, ?int $triggeredByUserId = null): BackupRestoreResult
    {
        if (! $backup->isSuccessful() || $backup->archive_path === null) {
            throw new RuntimeException('Only completed backups with an archive can be restored.');
        }

        $this->systemBackupManager->assertValidArchiveRelativePath($backup->archive_path);

        return $this->restoreTarget([
            'backup' => $backup,
            'archive_disk' => SystemBackupManager::ARCHIVE_DISK,
            'archive_path' => $backup->archive_path,
            'archive_filename' => $backup->archive_filename ?? basename((string) $backup->archive_path),
            'display_name' => 'backup #'.$backup->id.' ('.$backup->archive_filename.')',
        ], $triggeredByUserId);
    }

    public function latestRestoresForBackup(SystemBackup $backup, int $limit = 10)
    {
        if (! $this->hasRestoreTable()) {
            return collect();
        }

        return SystemBackupRestore::query()
            ->with(['triggeredBy', 'safetyBackup'])
            ->where('source_backup_id', $backup->id)
            ->latest()
            ->limit($limit)
            ->get();
    }

    private function restoreTarget(array $target, ?int $triggeredByUserId): BackupRestoreResult
    {
        $startedAt = now();
        $output = [];
        $temporaryDirectory = storage_path('app/temp/system-backup-restores/'.$startedAt->format('YmdHis').'-'.Str::lower(Str::random(8)));
        $extractedDirectory = $temporaryDirectory.'/archive';
        $sourceBackup = $target['backup'];
        $archiveDisk = $target['archive_disk'];
        $archivePath = $target['archive_path'];
        $archiveFilename = $target['archive_filename'];
        $displayName = $target['display_name'];
        $restoreRecord = null;

        File::ensureDirectoryExists($temporaryDirectory);

        try {
            $output[] = 'Preparing restore from '.$displayName.'.';

            $disk = Storage::disk($archiveDisk);
            $resolvedArchivePath = $disk->path($archivePath);
            $inspection = $this->archiveInspector->inspect($resolvedArchivePath);
            $output[] = 'Archive validation passed.';

            // Restores consume the existing listed archive directly. The only new archive
            // expected in this flow is the mandatory pre-restore safety backup.
            $safetyBackup = $this->systemBackupManager->createRestoreSafetyBackup(
                $triggeredByUserId,
                'Pre-restore safety backup before restoring '.$archiveFilename,
            );

            $output[] = 'Created pre-restore safety backup #'.$safetyBackup->id.' ('.$safetyBackup->archive_filename.').';

            $this->archiveExtractor->extractTo($resolvedArchivePath, $extractedDirectory);
            $output[] = 'Archive extracted to temporary restore workspace.';

            $this->databaseRestoreRunner->restoreFrom($extractedDirectory.DIRECTORY_SEPARATOR.$inspection->databaseSqlPath, $output);

            if ($inspection->includesUploads && $inspection->uploadsRootPath !== null) {
                $this->restoreUploads(
                    $extractedDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $inspection->uploadsRootPath),
                    $temporaryDirectory,
                    $output,
                );
            } else {
                $output[] = 'No uploads payload was present in this backup archive.';
            }

            $this->maintenanceRunner->run($output);

            $restoreRecord = $this->recordRestoreOutcome(
                status: SystemBackupRestore::STATUS_COMPLETED,
                sourceBackup: $sourceBackup,
                archiveDisk: $archiveDisk,
                archivePath: $archivePath,
                archiveFilename: $archiveFilename,
                safetyBackup: $safetyBackup,
                inspection: $inspection,
                startedAt: $startedAt,
                output: $output,
                triggeredByUserId: $triggeredByUserId,
            );

            return new BackupRestoreResult(
                sourceBackup: $sourceBackup,
                sourceArchivePath: $archivePath,
                sourceArchiveFilename: $archiveFilename,
                inspection: $inspection,
                safetyBackup: $safetyBackup,
                output: $output,
                restoreRecord: $restoreRecord,
            );
        } catch (Throwable $throwable) {
            $output[] = 'Restore failed: '.$throwable->getMessage();

            $this->recordRestoreOutcome(
                status: SystemBackupRestore::STATUS_FAILED,
                sourceBackup: $sourceBackup,
                archiveDisk: $archiveDisk,
                archivePath: $archivePath,
                archiveFilename: $archiveFilename,
                safetyBackup: $safetyBackup ?? null,
                inspection: $inspection ?? null,
                startedAt: $startedAt,
                output: $output,
                triggeredByUserId: $triggeredByUserId,
                errorMessage: $throwable->getMessage(),
            );

            throw new RuntimeException($throwable->getMessage(), previous: $throwable);
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }
    }

    private function resolveRestoreSource(string $reference): array
    {
        $backup = null;

        if (ctype_digit($reference)) {
            $backup = SystemBackup::query()->find((int) $reference);

            if ($backup instanceof SystemBackup) {
                if (! $backup->isSuccessful() || $backup->archive_path === null || $backup->archive_filename === null) {
                    throw new RuntimeException('Backup #'.$backup->id.' is not a completed archive that can be restored.');
                }

                return [
                    'backup' => $backup,
                    'archive_disk' => SystemBackupManager::ARCHIVE_DISK,
                    'archive_path' => $backup->archive_path,
                    'archive_filename' => $backup->archive_filename,
                    'display_name' => 'backup #'.$backup->id.' ('.$backup->archive_filename.')',
                ];
            }
        }

        $this->systemBackupManager->assertValidArchiveRelativePath($reference);

        if (! Storage::disk(SystemBackupManager::ARCHIVE_DISK)->exists($reference)) {
            throw new RuntimeException('Backup archive ['.$reference.'] does not exist on the backups disk.');
        }

        $backup = SystemBackup::query()
            ->where('archive_disk', SystemBackupManager::ARCHIVE_DISK)
            ->where('archive_path', $reference)
            ->first();

        return [
            'backup' => $backup,
            'archive_disk' => SystemBackupManager::ARCHIVE_DISK,
            'archive_path' => $reference,
            'archive_filename' => $backup?->archive_filename ?? basename($reference),
            'display_name' => $backup instanceof SystemBackup
                ? 'backup #'.$backup->id.' ('.$backup->archive_filename.')'
                : 'archive '.$reference,
        ];
    }

    private function restoreUploads(string $sourceDirectory, string $temporaryDirectory, array &$output): void
    {
        if (! File::isDirectory($sourceDirectory)) {
            throw new RuntimeException('Restore archive is missing uploads/public after extraction.');
        }

        $targetDirectory = (string) config('filesystems.disks.public.root');
        $rollbackDirectory = $temporaryDirectory.'/current-public-rollback';
        $hadExistingTarget = File::isDirectory($targetDirectory);

        File::ensureDirectoryExists(dirname($targetDirectory));

        if ($hadExistingTarget) {
            $this->moveDirectory($targetDirectory, $rollbackDirectory);
        }

        try {
            $this->moveDirectory($sourceDirectory, $targetDirectory);
            $output[] = 'Uploads restored to storage/app/public.';

            if ($hadExistingTarget) {
                File::deleteDirectory($rollbackDirectory);
            }
        } catch (Throwable $throwable) {
            File::deleteDirectory($targetDirectory);

            if ($hadExistingTarget && File::isDirectory($rollbackDirectory)) {
                $this->moveDirectory($rollbackDirectory, $targetDirectory);
            }

            throw $throwable;
        }
    }

    private function moveDirectory(string $from, string $to): void
    {
        if (@rename($from, $to)) {
            return;
        }

        if (! File::copyDirectory($from, $to)) {
            throw new RuntimeException('Directory move failed from '.$from.' to '.$to.'.');
        }

        File::deleteDirectory($from);
    }

    private function recordRestoreOutcome(
        string $status,
        ?SystemBackup $sourceBackup,
        string $archiveDisk,
        string $archivePath,
        string $archiveFilename,
        ?SystemBackup $safetyBackup,
        ?BackupRestoreInspection $inspection,
        $startedAt,
        array $output,
        ?int $triggeredByUserId,
        ?string $errorMessage = null,
    ): ?SystemBackupRestore {
        if (! $this->hasRestoreTable()) {
            return null;
        }

        $resolvedSourceBackupId = $this->resolveRestoredBackupReferenceId($sourceBackup);
        $resolvedSafetyBackupId = $this->resolveRestoredBackupReferenceId($safetyBackup);
        $finishedAt = now();

        return SystemBackupRestore::query()->create([
            'source_backup_id' => $resolvedSourceBackupId,
            'source_archive_disk' => $archiveDisk,
            'source_archive_path' => $archivePath,
            'source_archive_filename' => $archiveFilename,
            'safety_backup_id' => $resolvedSafetyBackupId,
            'status' => $status,
            'restored_parts' => $inspection?->restoredParts() ?? [],
            'manifest' => $inspection?->manifest,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'duration_ms' => $startedAt->diffInMilliseconds($finishedAt),
            'summary' => $status === SystemBackupRestore::STATUS_COMPLETED
                ? 'Restore completed from '.$archiveFilename.'.'
                : 'Restore failed for '.$archiveFilename.'.',
            'output' => implode(PHP_EOL, $output),
            'error_message' => $errorMessage,
            'triggered_by_user_id' => $triggeredByUserId,
        ]);
    }

    private function hasRestoreTable(): bool
    {
        return Schema::hasTable('system_backup_restores');
    }

    private function resolveRestoredBackupReferenceId(?SystemBackup $backup): ?int
    {
        if (! $backup instanceof SystemBackup || ! $backup->getKey()) {
            return null;
        }

        if (! Schema::hasTable('system_backups')) {
            return null;
        }

        return SystemBackup::query()->whereKey($backup->getKey())->exists()
            ? $backup->getKey()
            : null;
    }
}
