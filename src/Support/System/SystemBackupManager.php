<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use WebBlocks\Cms\Models\SystemBackup;

class SystemBackupManager
{
  public const GENERATED_ARCHIVE_PATTERN = '/\Awebblocks-cms-backup-\d{4}-\d{2}-\d{2}-\d{6}-[a-z0-9]{24}\.zip\z/';

  public const ARCHIVE_DISK = 'backups';

  public const RECENT_BACKUP_HOURS = 24;

  public const TYPE_RESTORE_SAFETY = 'restore_safety';

  private ?SystemBackupArchiveResolver $archiveResolver = null;

  public function __construct(
    private readonly DatabaseDumpWriter $databaseDumpWriter,
    private readonly BackupManifestBuilder $backupManifestBuilder,
    private readonly BackupArchiveBuilder $backupArchiveBuilder,
    ?SystemBackupArchiveResolver $archiveResolver = null,
  ) {
    $this->archiveResolver = $archiveResolver;
  }

  public function createManualBackup(?int $triggeredByUserId = null, ?string $label = null): SystemBackup
  {
    return $this->createBackup(SystemBackup::TYPE_MANUAL, $triggeredByUserId, $label);
  }

  public function createManagedManualBackup(array $retention, ?int $triggeredByUserId = null): SystemBackup
  {
    return $this->createBackup(SystemBackup::TYPE_MANUAL, $triggeredByUserId, retention: $retention);
  }

  public function createRestoreSafetyBackup(?int $triggeredByUserId = null, ?string $label = null): SystemBackup
  {
    return $this->createBackup(self::TYPE_RESTORE_SAFETY, $triggeredByUserId, $label);
  }

  public function createPreUpdateBackup(?int $triggeredByUserId = null, ?string $label = null): SystemBackup
  {
    return $this->createBackup(SystemBackup::TYPE_PRE_UPDATE, $triggeredByUserId, $label);
  }

  public function createContentApplyRestorePoint(?int $triggeredByUserId = null, ?string $label = null): SystemBackup
  {
    return $this->createBackup(SystemBackup::TYPE_CONTENT_APPLY, $triggeredByUserId, $label);
  }

  public function reserveManualBackup(array $retention, int $actorId): SystemBackup
  {
    return app(BackupOperations::class)->run(function () use ($retention, $actorId): SystemBackup {
      if (SystemBackup::query()->where('status', SystemBackup::STATUS_RUNNING)->where('type', SystemBackup::TYPE_MANUAL)
        ->whereNull('started_at')->where('created_at', '>=', now()->subSeconds(SystemBackup::WORKER_START_TIMEOUT_SECONDS))->exists()) {
        throw new BackupOperationException(__('webblocks-cms::backup-retention.busy'));
      }

      return $this->reserveBackup(SystemBackup::TYPE_MANUAL, $actorId, null, $retention);
    });
  }

  public function assertValidArchiveRelativePath(string $path): void
  {
    $this->backupArchiveResolver()->assertValidArchivePath($path);
  }

  private function createBackup(string $type, ?int $triggeredByUserId = null, ?string $label = null, array $retention = []): SystemBackup
  {
    return app(BackupOperations::class)->run(fn () => $this->buildArchive(
      $this->reserveBackup($type, $triggeredByUserId, $label, $retention)
    ));
  }

  private function reserveBackup(string $type, ?int $actorId, ?string $label, array $retention): SystemBackup
  {
    $filename = 'webblocks-cms-backup-'.now()->format('Y-m-d-His').'-'.Str::lower(Str::random(24)).'.zip';

    return SystemBackup::query()->create([
      ...$retention,
      'type' => $type,
      'status' => SystemBackup::STATUS_RUNNING,
      'label' => $label,
      'includes_database' => true,
      'includes_uploads' => true,
      'archive_disk' => self::ARCHIVE_DISK,
      'archive_path' => $filename,
      'archive_filename' => $filename,
      'triggered_by_user_id' => $actorId,
    ]);
  }

  public function runReservedManualBackup(int $id, string $filename): bool
  {
    if (! preg_match(self::GENERATED_ARCHIVE_PATTERN, $filename)) {
      return false;
    }

    return app(BackupOperations::class)->run(function () use ($id, $filename): bool {
      $backup = SystemBackup::query()->whereKey($id)->where('type', SystemBackup::TYPE_MANUAL)
        ->where('archive_filename', $filename)->where('archive_path', $filename)
        ->where('status', SystemBackup::STATUS_RUNNING)->whereNull('started_at')->first();

      if ($backup === null || $backup->hasExceededStartOrRunWindow()) {
        return false;
      }

      $startedAt = now();
      $claimed = SystemBackup::query()->whereKey($id)->where('archive_filename', $filename)
        ->where('status', SystemBackup::STATUS_RUNNING)->whereNull('started_at')
        ->update(['started_at' => $startedAt]);

      return $claimed === 1 && $this->buildArchive($backup->refresh())->isSuccessful();
    });
  }

  private function buildArchive(SystemBackup $backup): SystemBackup
  {
    $startedAt = $backup->started_at ?? now();
    $output = [];
    $archiveRelativePath = $backup->archive_path;
    $temporaryDirectory = storage_path('app/temp/system-backups/'.$startedAt->format('YmdHis').'-'.Str::lower(Str::random(8)));
    $databaseDumpPath = $temporaryDirectory.'/database.sql';

    try {
      $archiveDisk = $this->archiveDisk();
      $archivePath = $archiveDisk->path($archiveRelativePath);

      File::ensureDirectoryExists($temporaryDirectory);
      File::ensureDirectoryExists((string) config('filesystems.disks.public.root'));

      if ($backup->started_at === null) {
        $backup->update(['started_at' => $startedAt]);
      }

      $databaseMeta = $this->databaseDumpWriter->dumpTo($databaseDumpPath, $output);
      $manifest = $this->backupManifestBuilder->build($backup, $databaseMeta, $backup->archive_filename);
      $archiveMeta = $this->backupArchiveBuilder->build($archivePath, $databaseDumpPath, $manifest, $output);

      app(BackupArchiveValidation::class)->validate($backup, output: $output);
      $this->finalizeCompletedBackup($backup, $startedAt, $archiveDisk->size($archiveRelativePath), $archiveMeta, $output);

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
    return $this->backupArchiveResolver()->archiveDisk();
  }

  private function finalizeCompletedBackup(SystemBackup $backup, $startedAt, int $size, array $archiveMeta, array $output): void
  {
    $finishedAt = now();

    $backup->update([
      'status' => SystemBackup::STATUS_COMPLETED,
      'archive_size_bytes' => $size,
      'finished_at' => $finishedAt,
      'duration_ms' => $startedAt->diffInMilliseconds($finishedAt),
      'summary' => 'Backup completed with database dump and '.number_format($archiveMeta['uploads_file_count']).' upload file(s).',
      'output' => implode(PHP_EOL, $output),
      'error_message' => null,
    ]);
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

    if (app(BackupOperations::class)->isBusy()) {
      return;
    }

    try {
      app(BackupOperations::class)->run(function (): void {
        SystemBackup::query()->where('status', SystemBackup::STATUS_RUNNING)->whereNull('finished_at')
          ->eachById(function (SystemBackup $backup): void {
            if ($backup->hasExceededStartOrRunWindow()) {
              $this->recordInterruption($backup);
            }
          });

        // Earlier versions wrote this marker before starting the database dump.
        SystemBackup::query()->where('status', SystemBackup::STATUS_COMPLETED)->whereNull('archive_size_bytes')
          ->where('output', 'Backup record marked as completed before database snapshot.')
          ->eachById(function (SystemBackup $backup): void {
            if ($this->archiveResolution($backup)->isMissing()) {
              $this->recordInterruption($backup);
            }
          });
      });
    } catch (BackupOperationException) {
      // A backup or restore took the lease after the idle check.
    }
  }

  private function recordInterruption(SystemBackup $backup): void
  {
    $message = __('webblocks-cms::backup-retention.worker_interrupted');
    $backup->update([
      'status' => SystemBackup::STATUS_FAILED,
      'summary' => $message,
      'finished_at' => now(),
      'output' => trim($backup->output.PHP_EOL.$message),
      'error_message' => $message,
    ]);
  }

  public function downloadResponse(SystemBackup $backup): BinaryFileResponse
  {
    $resolution = $this->backupArchiveResolver()->resolveForBackup($backup);

    if (! $resolution->isAvailable()) {
      abort($resolution->isUnsafe() ? 403 : 404, $resolution->feedbackMessage());
    }

    return response()->download($resolution->absolutePath, $backup->archive_filename, [
      'Content-Type' => 'application/zip',
    ]);
  }

  public function archiveResolution(SystemBackup $backup, bool $requireReadableFile = true): SystemBackupArchiveResolution
  {
    return $this->backupArchiveResolver()->resolveForBackup($backup, $requireReadableFile);
  }

  public function deleteBackupRecord(SystemBackup $backup, bool $allowRunning = false): void
  {
    app(BackupOperations::class)->run(function () use ($backup, $allowRunning): void {
      $backup->refresh();
      if ($backup->is_protected) {
        throw new BackupOperationException(__('webblocks-cms::backup-retention.protected_error'));
      }
      $this->deleteBackupUnderLease($backup, $allowRunning);
    });
  }

  private function deleteBackupUnderLease(SystemBackup $backup, bool $allowRunning): void
  {
    if ($backup->isRunning() && ! $backup->hasExceededStartOrRunWindow() && ! $allowRunning) {
      throw new RuntimeException('Running backup cannot be deleted unless you explicitly confirm it is stuck.');
    }

    $storedArchivePath = $backup->archiveRelativePath();

    if ($storedArchivePath !== null) {
      $this->deleteArchiveIfPresent($backup, $storedArchivePath);
    }

    $backup->delete();
  }

  private function deleteArchiveIfPresent(SystemBackup $backup, string $archivePath): void
  {
    $resolution = $this->backupArchiveResolver()->resolvePath($archivePath, requireReadableFile: false);
    if ($resolution->isAvailable()) {
      foreach (SystemBackup::query()->where('id', '!=', $backup->id)->whereNotNull('archive_path')->get(['id', 'archive_path']) as $other) {
        $otherResolution = $this->backupArchiveResolver()->resolvePath($other->archive_path, requireReadableFile: false);
        if ($otherResolution->isAvailable() && $otherResolution->absolutePath === $resolution->absolutePath) {
          return; // Delete this record only; another record still owns the file.
        }
      }
    }

    if ($resolution->isUnsafe()) {
      throw new RuntimeException($resolution->feedbackMessage());
    }

    if ($resolution->isMissing()) {
      return;
    }

    if (! $resolution->isAvailable() || $resolution->absolutePath === null || ! @unlink($resolution->absolutePath)) {
      Log::warning('Backup archive file could not be deleted.', [
        'backup_id' => $backup->id,
        'archive_disk' => self::ARCHIVE_DISK,
        'archive_status' => $resolution->status,
      ]);

      throw new RuntimeException('Backup archive file could not be deleted.');
    }
  }

  private function hasBackupTable(): bool
  {
    return Schema::hasTable('wbcms_system_backups');
  }

  private function backupArchiveResolver(): SystemBackupArchiveResolver
  {
    if (! $this->archiveResolver instanceof SystemBackupArchiveResolver) {
      $this->archiveResolver = app(SystemBackupArchiveResolver::class);
    }

    return $this->archiveResolver;
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
}
