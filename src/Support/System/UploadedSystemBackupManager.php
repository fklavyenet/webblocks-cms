<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use WebBlocks\Cms\Models\SystemBackup;

class UploadedSystemBackupManager
{
  public function __construct(
    private readonly BackupRestoreArchiveInspector $archiveInspector,
    private readonly SystemBackupManager $systemBackupManager,
  ) {}

  public function import(UploadedFile $file, ?int $triggeredByUserId = null): SystemBackup
  {
    return app(BackupOperations::class)->run(fn () => $this->importUnderLease($file, $triggeredByUserId));
  }

  private function importUnderLease(UploadedFile $file, ?int $triggeredByUserId = null): SystemBackup
  {
    $startedAt = now();
    $sourceFilename = $file->getClientOriginalName();
    $safeFilename = Str::lower(Str::random(8)).'-webblocks-cms-backup-upload-'.$startedAt->format('Y-m-d-His').'.zip';
    $archivePath = $safeFilename;
    $disk = $this->systemBackupManager->archiveDisk();

    $storedPath = $disk->putFileAs('', $file, $archivePath);

    if (! is_string($storedPath)) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.archive_store'));
    }

    $backup = null;
    try {
      $inspection = $this->archiveInspector->inspect($disk->path($storedPath));
      $archiveSize = $disk->size($storedPath);
      $manifest = $inspection->manifest;
      $output = [
        __('webblocks-cms::admin-feedback.backups.uploaded', [], 'en'),
        __('webblocks-cms::admin-feedback.panel_copy.backup_log_source_filename', ['value' => $sourceFilename], 'en'),
        __('webblocks-cms::admin-feedback.panel_copy.backup_validation_passed', [], 'en'),
      ];

      $backup = SystemBackup::query()->create([
        'type' => SystemBackup::TYPE_UPLOADED,
        'status' => SystemBackup::STATUS_RUNNING,
        'label' => $sourceFilename,
        'includes_database' => $inspection->includesDatabase,
        'includes_uploads' => $inspection->includesUploads,
        'archive_disk' => SystemBackupManager::ARCHIVE_DISK,
        'archive_path' => $storedPath,
        'archive_filename' => basename($storedPath),
        'archive_size_bytes' => is_int($archiveSize) ? $archiveSize : null,
        'started_at' => $startedAt,
        'triggered_by_user_id' => $triggeredByUserId,
      ]);
      app(BackupArchiveValidation::class)->validate($backup, alreadyInspected: true);

      $finishedAt = now();
      $backup->update([
        'status' => SystemBackup::STATUS_COMPLETED,
        'finished_at' => $finishedAt,
        'duration_ms' => $startedAt->diffInMilliseconds($finishedAt),
        'summary' => __('webblocks-cms::admin-feedback.backups.uploaded_summary', [], 'en'),
        'output' => implode(PHP_EOL, array_merge($output, $this->manifestSummaryLines($manifest, $inspection))),
      ]);

      return $backup;
    } catch (Throwable $throwable) {
      $disk->delete($storedPath);
      $backup?->delete();

      throw new RuntimeException($throwable->getMessage(), previous: $throwable);
    }
  }

  private function manifestSummaryLines(array $manifest, BackupRestoreInspection $inspection): array
  {
    $text = fn (string $key, array $replace = []) => __('webblocks-cms::admin-feedback.panel_copy.backup_log_'.$key, $replace, 'en');

    return array_values(array_filter([
      $text('manifest_app', ['value' => $manifest['app_name'] ?? $text('unknown')]),
      isset($manifest['app_version']) ? $text('manifest_version', ['value' => $manifest['app_version']]) : null,
      isset($manifest['created_at']) ? $text('manifest_created_at', ['value' => $manifest['created_at']]) : null,
      $text('contents', ['value' => implode(' + ', $inspection->restoredParts())]),
    ]));
  }
}
