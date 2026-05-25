<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use WebBlocks\Cms\Models\SystemBackup;

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
    $output = [];
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
    $archiveRelativePath = $archiveFilename;

    try {
      $archiveDisk = $this->archiveDisk();
      $archivePath = $archiveDisk->path($archiveRelativePath);

      File::ensureDirectoryExists($temporaryDirectory);
      File::ensureDirectoryExists((string) config('filesystems.disks.public.root'));

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
      $sanitizedFailureDetail = $this->sanitizeFailureDetail($throwable->getMessage());

      try {
        $this->archiveDisk()->delete($archiveRelativePath);
      } catch (Throwable) {
        // Keep the original backup failure detail as the primary signal.
      }

      $output[] = 'Backup failed: '.$sanitizedFailureDetail;
      $finishedAt = now();

      $backup->forceFill([
        'status' => SystemBackup::STATUS_FAILED,
        'finished_at' => $finishedAt,
        'duration_ms' => $startedAt->diffInMilliseconds($finishedAt),
        'summary' => 'Backup failed.',
        'output' => implode(PHP_EOL, $output),
        'error_message' => $sanitizedFailureDetail,
      ])->save();

      throw new RuntimeException($sanitizedFailureDetail, previous: $throwable);
    } finally {
      File::deleteDirectory($temporaryDirectory);
    }
  }

  public function archiveDisk(): FilesystemAdapter
  {
    $configuredRoot = config('filesystems.disks.'.self::ARCHIVE_DISK.'.root');
    $archiveRoot = is_string($configuredRoot) && trim($configuredRoot) !== ''
      ? $configuredRoot
      : storage_path('app/backups');

    config()->set('filesystems.disks.'.self::ARCHIVE_DISK, array_merge(
      [
        'driver' => 'local',
        'root' => $archiveRoot,
        'throw' => false,
        'report' => false,
      ],
      (array) config('filesystems.disks.'.self::ARCHIVE_DISK, [])
    ));

    File::ensureDirectoryExists($archiveRoot);

    return Storage::disk(self::ARCHIVE_DISK);
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

    $disk = Storage::disk(self::ARCHIVE_DISK);

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

    $storedArchivePath = $backup->archiveRelativePath();

    if ($storedArchivePath !== null) {
      $this->assertValidArchiveRelativePath($storedArchivePath);
      $this->deleteArchiveIfPresent($backup, self::ARCHIVE_DISK, $storedArchivePath);
    }

    $backup->delete();
  }

  private function deleteArchiveIfPresent(SystemBackup $backup, string $diskName, string $archivePath): void
  {
    $disk = Storage::disk($diskName);
    $diskRoot = $this->resolvedDiskRoot($diskName);

    if (! $disk->exists($archivePath)) {
      return;
    }

    if (! $disk->delete($archivePath) || $disk->exists($archivePath)) {
      Log::warning('Backup archive file could not be deleted.', [
        'backup_id' => $backup->id,
        'archive_disk' => $diskName,
        'archive_path' => $archivePath,
        'disk_root' => $diskRoot,
      ]);

      throw new RuntimeException('Backup archive file could not be deleted.');
    }
  }

  private function resolvedDiskRoot(string $diskName): ?string
  {
    $root = realpath(Storage::disk($diskName)->path(''));

    return $root === false ? null : $root;
  }

  private function hasBackupTable(): bool
  {
    return Schema::hasTable('system_backups');
  }

  private function sanitizeFailureDetail(string $message): string
  {
    $sanitized = preg_replace('/([?&](?:password|passwd|pwd|token|api[_-]?key|secret)=)[^&\s]+/i', '$1[redacted]', $message) ?? $message;
    $sanitized = preg_replace('/\b(password|passwd|pwd|token|api[_-]?key|secret)=\S+/i', '$1=[redacted]', $sanitized) ?? $sanitized;
    $sanitized = preg_replace('/--defaults-extra-file=\S+/i', '--defaults-extra-file=[redacted]', $sanitized) ?? $sanitized;
    $sanitized = str_replace(storage_path(), '[storage_path]', $sanitized);
    $sanitized = preg_replace('/\b([A-Z_]*(?:PASSWORD|TOKEN|SECRET|APP_KEY))=[^\s]+/i', '$1=[redacted]', $sanitized) ?? $sanitized;
    $sanitized = str_replace(base_path(), '[base_path]', $sanitized);

    return trim($sanitized) !== '' ? $sanitized : 'Backup failed for an unknown reason.';
  }

  private function hasInvalidRelativePath(string $path): bool
  {
    return str_contains($path, '..')
      || str_starts_with($path, '/')
      || preg_match('/^[A-Za-z]:\\\\/', $path) === 1;
  }
}
