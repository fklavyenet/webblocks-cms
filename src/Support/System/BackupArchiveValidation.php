<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;
use WebBlocks\Cms\Models\SystemBackup;

/** Validation provenance for reports only. Destructive operations still inspect the archive. */
class BackupArchiveValidation
{
  public const TABLE = 'wbcms_backup_archive_validations';

  public const VERSION = 1;

  public static function migrate(): void
  {
    if (! Schema::hasTable(self::TABLE)) {
      Schema::create(self::TABLE, function (Blueprint $table): void {
        $table->foreignId('backup_id')->primary()->constrained('wbcms_system_backups')->cascadeOnDelete();
        $table->char('signature', 64);
        $table->char('sha256', 64)->nullable();
        $table->unsignedSmallInteger('validator_version');
        $table->string('status', 16);
        $table->timestamp('checked_at');
      });
    }
  }

  public function records(array $ids): Collection
  {
    return Schema::hasTable(self::TABLE)
      ? DB::table(self::TABLE)->whereIn('backup_id', $ids)->get()->keyBy('backup_id')
      : collect();
  }

  public function status(?object $record, string $path): string
  {
    return $record && (int) $record->validator_version === self::VERSION && hash_equals($record->signature, $this->signature($path))
      ? $record->status : 'pending';
  }

  private function signature(string $path): string
  {
    clearstatcache(true, $path);
    $stat = @stat($path);
    if ($stat === false || ! is_file($path)) {
      throw new RuntimeException('Backup archive is unavailable.');
    }

    return hash('sha256', implode(':', [realpath($path), $stat['dev'], $stat['ino'], $stat['size'], $stat['mtime'], $stat['ctime']]));
  }

  public function validate(SystemBackup $backup, bool $alreadyInspected = false): void
  {
    if (! Schema::hasTable(self::TABLE)) {
      return; // An incomplete upgrade cannot make an unverified archive trusted by reports.
    }
    $path = app(SystemBackupArchiveResolver::class)->resolveForRetention($backup)->absolutePath;
    if ($path === null) {
      return;
    }
    $signature = $this->signature($path);
    $status = 'valid';
    $digest = null;
    try {
      if (! $alreadyInspected) {
        app(BackupRestoreArchiveInspector::class)->inspect($path);
      }
      $digest = hash_file('sha256', $path);
      if ($digest === false || $signature !== $this->signature($path)) {
        throw new RuntimeException('Backup archive changed during validation.');
      }
    } catch (Throwable $exception) {
      $status = 'invalid';
      throw $exception;
    } finally {
      try {
        DB::table(self::TABLE)->updateOrInsert(['backup_id' => $backup->id], [
        'signature' => $signature, 'sha256' => $status === 'valid' ? $digest : null,
        'validator_version' => self::VERSION, 'status' => $status, 'checked_at' => now(),
      ]);
      } catch (Throwable) {
        // Optional reporting provenance cannot discard an otherwise valid backup.
      }
    }
  }

  /** Bound the expensive upgrade backfill; never read SQL streams during a panel GET. */
  public function validatePending(int $limit = 8): int
  {
    if (! Schema::hasTable(self::TABLE) || ! Schema::hasTable('wbcms_system_backups')) {
      return 0;
    }
    $limit = max(1, min(100, $limit));
    $count = 0;
    foreach (SystemBackup::query()->where('status', SystemBackup::STATUS_COMPLETED)
      ->whereIn('type', ['manual', 'pre_update', 'restore_safety', 'content_apply'])->lazyById(100) as $backup) {
      try {
        $path = app(SystemBackupArchiveResolver::class)->resolveForRetention($backup)->absolutePath;
        if ($path === null || $this->status($this->records([$backup->id])->get($backup->id), $path) !== 'pending') {
          continue;
        }
        $count++;
        $this->validate($backup);
      } catch (Throwable) {
        // Invalid archives are recorded and never occupy a recovery floor.
      }
      if ($count >= $limit) {
        break;
      }
    }

    return $count;
  }
}
