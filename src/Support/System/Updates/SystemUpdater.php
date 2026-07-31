<?php

namespace WebBlocks\Cms\Support\System\Updates;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Models\SystemBackupRestore;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteTransferPackage;
use WebBlocks\Cms\Support\System\BackupRestoreArchiveExtractor;
use WebBlocks\Cms\Support\System\BackupRestoreArchiveInspector;
use WebBlocks\Cms\Support\System\BackupRestoreInspection;
use WebBlocks\Cms\Support\System\BackupRestoreResult;
use WebBlocks\Cms\Support\System\DatabaseExecutionStrategyResolver;
use WebBlocks\Cms\Support\System\DatabaseRestoreRunner;
use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\System\SqlDumpContentValidator;
use WebBlocks\Cms\Support\System\SystemBackupArchiveResolution;
use WebBlocks\Cms\Support\System\SystemBackupArchiveResolver;
use WebBlocks\Cms\Support\System\SystemBackupManager;
use WebBlocks\Cms\Support\System\SystemBackupRestoreMaintenanceRunner;
use WebBlocks\Cms\Support\System\SystemBackupRestoreManager;

/**
 * One-click self-update pipeline. A single lock is held across the whole flow:
 *
 *   lock → check → validate → backup → download → verify(checksum + signature)
 *        → extract → [maintenance down] → apply → deps → post-install
 *        → verify applied version → [maintenance up] → persist → record → prune
 *
 * On failure it recovers maintenance mode and, when the failure happened after
 * package apply began, best-effort restores the pre-update backup. A successful
 * restore records the run as `restored`; a failed restore keeps the run `failed`
 * with both sanitized error trails in the run log.
 */
class SystemUpdater
{
  public function __construct(
    private readonly UpdateServerClient $updateServerClient,
    private readonly InstalledVersionStore $installedVersionStore,
    private readonly UpdateWorkspaceManager $workspaceManager,
    private readonly UpdatePackageDownloader $packageDownloader,
    private readonly UpdatePackageExtractor $packageExtractor,
    private readonly UpdateSignatureVerifier $signatureVerifier,
    private readonly UpdateInstaller $updateInstaller,
    private readonly SystemBackupManager $systemBackupManager,
    private readonly SystemBackupRestoreManager $backupRestoreManager,
    private readonly SystemUpdateRunRetention $runRetention,
  ) {}

  public function run(User $user): UpdateResult
  {
    $lock = Cache::lock($this->lockName(), (int) config('webblocks-updates.installer.lock_ttl_seconds', 900));

    if (! $lock->get()) {
      throw new UpdateException('Another update is already running. Wait for it to finish before starting a new run.');
    }

    $workspace = null;
    $maintenanceEnabled = false;
    $applyStarted = false;
    $backup = null;
    $run = null;
    $output = [];
    $warningCount = 0;
    $startedAt = CarbonImmutable::now();
    $fromVersion = $this->installedVersionStore->currentVersion();
    $toVersion = $fromVersion;

    // Applying the package replaces this very class tree on disk, so everything
    // the failure path may need must be resident in memory before apply starts.
    $this->preloadSelfHandledClasses();

    try {
      if (! Schema::hasTable('wbcms_system_update_runs')) {
        throw new UpdateException('System update logging is not ready. Run the latest migrations before updating.');
      }

      $checkResult = $this->updateServerClient->check();
      $release = $checkResult->release;

      if (! $checkResult->updateAvailable || ! is_array($release)) {
        throw new UpdateException('No compatible update is available right now.');
      }

      if (($checkResult->compatibility['status'] ?? 'unknown') === 'incompatible') {
        throw new UpdateException('This install is not compatible with the latest published release yet.');
      }

      $downloadUrl = trim((string) ($release['download_url'] ?? ''));

      if ($downloadUrl === '') {
        throw new UpdateException('The latest release does not provide a downloadable package.');
      }

      $toVersion = trim((string) ($release['version'] ?? $checkResult->latestVersion ?? ''));

      if ($toVersion === '') {
        throw new UpdateException('The latest release metadata is incomplete and cannot be installed.');
      }

      $backupLabel = sprintf(
        'Pre-update backup before %s -> %s at %s',
        $fromVersion,
        $toVersion,
        CarbonImmutable::now()->format('Y-m-d H:i:s')
      );

      try {
        $backup = $this->systemBackupManager->createPreUpdateBackup($user->getKey(), $backupLabel);
      } catch (Throwable $throwable) {
        $sanitizedFailureDetail = $this->sanitizeFailureDetail($throwable->getMessage());

        $failure = new UpdateException(
          'Update was not installed because the pre-update backup could not be created.',
          $sanitizedFailureDetail,
          previous: $throwable,
        );

        $this->recordPreparationFailure($user, $fromVersion, $toVersion, $failure);

        throw $failure;
      }

      $run = new SystemUpdateRun([
        'from_version' => $fromVersion,
        'to_version' => $toVersion,
        'status' => SystemUpdateRun::STATUS_FAILED,
        'summary' => 'Update started.',
        'started_at' => $startedAt,
        'triggered_by_user_id' => $user->getKey(),
      ]);
      $run->save();

      $output[] = 'Starting update from '.$fromVersion.' to '.$toVersion.'.';
      $output[] = 'Pre-update backup created: #'.$backup->id.' '.$backup->archive_filename;

      $workspace = $this->workspaceManager->create();
      $output[] = 'Workspace ready at '.$workspace['root'];

      $this->packageDownloader->download($downloadUrl, $workspace['archive']);
      $output[] = 'Package downloaded to '.$workspace['archive'];

      $warningCount += $this->verifyChecksum($workspace['archive'], $release, $output);
      $this->signatureVerifier->verify((string) ($release['checksum_sha256'] ?? ''), $release, $output);
      $packageRoot = $this->packageExtractor->extract($workspace['archive'], $workspace['extract']);
      $output[] = 'Package extracted to '.$packageRoot;

      $this->updateInstaller->enterMaintenance($output);
      $maintenanceEnabled = true;

      $applyStarted = true;
      $this->updateInstaller->applyPackage($packageRoot, $output);
      $this->updateInstaller->installDependencies($output);
      $this->updateInstaller->runPostInstallCommands($output);
      $this->updateInstaller->verifyAppliedVersion($toVersion, $output);
      $this->updateInstaller->finalizeAppliedPackage($output);
      $this->updateInstaller->leaveMaintenance($output);
      $maintenanceEnabled = false;

      $this->installedVersionStore->persist($toVersion);
      $output[] = 'Installed version persisted as '.$toVersion;

      $finishedAt = CarbonImmutable::now();
      $durationMs = $this->normalizeDurationMs($startedAt->diffInMilliseconds($finishedAt));
      $status = $warningCount > 0 ? SystemUpdateRun::STATUS_SUCCESS_WITH_WARNINGS : SystemUpdateRun::STATUS_SUCCESS;
      $summary = $warningCount > 0
        ? 'Updated to '.$toVersion.' with '.$warningCount.' warning(s).'
        : 'Updated to '.$toVersion.' successfully.';

      $this->persistRun($run, $status, $summary, $output, $warningCount, $finishedAt, $durationMs);

      Log::info('System update completed.', [
        'from_version' => $fromVersion,
        'to_version' => $toVersion,
        'status' => $status,
        'warning_count' => $warningCount,
      ]);

      $this->runRetention->prune();

      return new UpdateResult(
        fromVersion: $fromVersion,
        toVersion: $toVersion,
        status: $status,
        summary: $summary,
        output: implode(PHP_EOL, $output),
        warningCount: $warningCount,
        startedAt: $startedAt,
        finishedAt: $finishedAt,
        durationMs: $durationMs,
        preUpdateBackup: $backup,
      );
    } catch (Throwable $throwable) {
      if ($applyStarted) {
        try {
          $this->updateInstaller->rollbackAppliedPackage($output);
        } catch (Throwable $rollbackException) {
          $output[] = 'Package rollback failed: '.$rollbackException->getMessage();
        }
      }

      if ($maintenanceEnabled) {
        try {
          $this->updateInstaller->leaveMaintenance($output);
          $output[] = 'Maintenance mode recovered after failure.';
        } catch (Throwable $recoveryException) {
          $output[] = 'Maintenance recovery failed: '.$recoveryException->getMessage();
        }
      }

      $failure = $throwable instanceof UpdateException
        ? $throwable
        : new UpdateException(
          'The update failed. Review the latest update log for details.',
          $throwable->getMessage(),
          previous: $throwable,
        );

      $output[] = 'Update failed: '.$this->sanitizeFailureDetail($failure->getMessage());

      $status = SystemUpdateRun::STATUS_FAILED;
      $summary = $failure->userMessage();

      if ($applyStarted && $backup instanceof SystemBackup) {
        // The live code may already be partially overwritten; best-effort roll
        // back to the pre-update backup that was created moments ago.
        try {
          $this->backupRestoreManager->restoreFromBackup($backup, $user->getKey());
          $status = SystemUpdateRun::STATUS_RESTORED;
          $summary = $failure->userMessage().' The pre-update backup was restored automatically.';
          $output[] = 'Pre-update backup #'.$backup->id.' restored after failure.';
        } catch (Throwable $restoreException) {
          $output[] = 'Automatic restore of pre-update backup #'.$backup->id.' failed: '
            .$this->sanitizeFailureDetail($restoreException->getMessage());
          $output[] = 'Restore the pre-update backup manually from the Backups screen.';
        }
      }

      $finishedAt = CarbonImmutable::now();
      $durationMs = $this->normalizeDurationMs($startedAt->diffInMilliseconds($finishedAt));

      if ($run instanceof SystemUpdateRun) {
        $this->persistRun($run, $status, $summary, $output, $warningCount, $finishedAt, $durationMs);
        $this->runRetention->prune();
      }

      Log::error('System update failed.', [
        'from_version' => $fromVersion,
        'to_version' => $toVersion,
        'status' => $status,
        'error' => $failure->getMessage(),
      ]);

      throw $failure;
    } finally {
      $this->workspaceManager->cleanup($workspace['root'] ?? null);
      $this->releaseLock($lock);
    }
  }

  public function isLocked(): bool
  {
    $lock = Cache::lock($this->lockName(), 1);

    if ($lock->get()) {
      $this->releaseLock($lock);

      return false;
    }

    return true;
  }

  private function verifyChecksum(string $archivePath, array $release, array &$output): int
  {
    $expectedChecksum = strtolower(trim((string) ($release['checksum_sha256'] ?? '')));

    if ($expectedChecksum === '') {
      // Applying an update overwrites the CMS package code on the live site, so
      // an unverifiable package is a remote-code-execution risk. Refuse to apply
      // a release whose metadata does not include a SHA-256 checksum instead of
      // continuing with only a warning.
      throw new UpdateException(
        'The update was blocked because the release did not include a package checksum to verify against.',
        'Release metadata is missing checksum_sha256; refusing to apply an unverifiable package.'
      );
    }

    $actualChecksum = hash_file('sha256', $archivePath);

    if (! is_string($actualChecksum) || $actualChecksum === '') {
      throw new UpdateException('The downloaded package could not be verified.', 'SHA-256 checksum generation failed.');
    }

    if (! hash_equals($expectedChecksum, strtolower($actualChecksum))) {
      throw new UpdateException('The downloaded package failed checksum verification.', 'Checksum mismatch for downloaded package.');
    }

    $output[] = 'Package checksum verified: '.$actualChecksum;

    return 0;
  }

  private function normalizeDurationMs(int|float $durationMs): int
  {
    return (int) round($durationMs);
  }

  private function persistRun(SystemUpdateRun $run, string $status, string $summary, array $output, int $warningCount, CarbonImmutable $finishedAt, int $durationMs): void
  {
    $run->forceFill([
      'status' => $status,
      'summary' => $summary,
      'output' => implode(PHP_EOL, $output),
      'warning_count' => $warningCount,
      'finished_at' => $finishedAt,
      'duration_ms' => $durationMs,
    ])->save();
  }

  /**
   * Eagerly load every class the failure path can touch after applyPackage has
   * replaced the package code on disk. A future self-update must never fatal
   * because a restore collaborator would be autoloaded from the new (possibly
   * incompatible or half-written) tree mid-failure.
   */
  private function preloadSelfHandledClasses(): void
  {
    foreach ([
      UpdateException::class,
      UpdateResult::class,
      SystemUpdateRun::class,
      SystemBackup::class,
      SystemBackupRestore::class,
      SystemBackupRestoreManager::class,
      BackupRestoreArchiveExtractor::class,
      BackupRestoreArchiveInspector::class,
      BackupRestoreInspection::class,
      BackupRestoreResult::class,
      DatabaseRestoreRunner::class,
      DatabaseExecutionStrategyResolver::class,
      SqlDumpContentValidator::class,
      SystemBackupRestoreMaintenanceRunner::class,
      SystemBackupArchiveResolver::class,
      SystemBackupArchiveResolution::class,
      SystemBackupManager::class,
      SiteTransferPackage::class,
    ] as $class) {
      class_exists($class);
    }
  }

  private function lockName(): string
  {
    return (string) config('webblocks-updates.installer.lock_name', 'system-updates:run');
  }

  private function releaseLock(Lock $lock): void
  {
    try {
      $lock->release();
    } catch (Throwable) {
      // Ignore lock release failures so the original update result is preserved.
    }
  }

  private function recordPreparationFailure(User $user, string $fromVersion, ?string $toVersion, UpdateException $failure): void
  {
    if (! Schema::hasTable('wbcms_system_update_runs')) {
      return;
    }

    $finishedAt = CarbonImmutable::now();

    SystemUpdateRun::query()->create([
      'from_version' => $fromVersion,
      'to_version' => $toVersion ?: $fromVersion,
      'status' => SystemUpdateRun::STATUS_FAILED,
      'summary' => $failure->userMessage(),
      'output' => implode(PHP_EOL, [
        'Pre-update backup failed before update apply started.',
        'Failure detail: '.$failure->getMessage(),
      ]),
      'warning_count' => 0,
      'started_at' => $finishedAt,
      'finished_at' => $finishedAt,
      'duration_ms' => 0,
      'triggered_by_user_id' => $user->getKey(),
    ]);

    $this->runRetention->prune();
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
