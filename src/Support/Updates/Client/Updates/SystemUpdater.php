<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Updates;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;
use WebBlocks\Cms\Support\Updates\Client\Contracts\ApplyStrategy;
use WebBlocks\Cms\Support\Updates\Client\Contracts\BackupManager;
use WebBlocks\Cms\Support\Updates\Client\Contracts\InstalledVersionStore;
use WebBlocks\Cms\Support\Updates\Client\Contracts\PostApplyRunner;
use WebBlocks\Cms\Support\Updates\Client\Contracts\RunRecorder;
use WebBlocks\Cms\Support\Updates\Client\Support\RunStatus;
use WebBlocks\Cms\Support\Updates\Client\Support\Version\VersionResolver;

/**
 * The update pipeline is decoupled from product models. One shared engine
 * drives the strategy-agnostic steps:
 *
 *   lock → check → validate → backup → download → verify(checksum + signature)
 *        → extract → [maintenance down] → apply(strategy) → post-apply
 *        → verify applied version → [maintenance up] → persist → record → prune
 *
 * On failure it recovers maintenance, restores the pre-update backup (restore-on-
 * failure through status `restored`), records the failed run and
 * rethrows. Collaborators are contracts with sensible defaults, so a product only
 * rebinds what it needs (DB run recorder, DB-aware backup, eager version store).
 */
class SystemUpdater
{
  /**
   * Fatal-death lock guard: a mid-run fatal (OOM,
   * timeout) skips `finally`, so the lock would sit until its TTL and every
   * web attempt would be rejected. Shutdown functions DO run after fatals —
   * while this flag is true the registered guard force-releases the lock and
   * clears the heartbeat so the very next attempt can run.
   */
  private static bool $runInFlight = false;

  private static bool $shutdownGuardRegistered = false;

  public function __construct(
    private readonly UpdateServerClient $client,
    private readonly UpdateWorkspaceManager $workspaceManager,
    private readonly UpdatePackageDownloader $downloader,
    private readonly UpdatePackageExtractor $extractor,
    private readonly UpdateSignatureVerifier $signatureVerifier,
    private readonly ApplyStrategy $applyStrategy,
    private readonly VersionResolver $versions,
    private readonly BackupManager $backupManager,
    private readonly RunRecorder $runRecorder,
    private readonly InstalledVersionStore $versionStore,
    private readonly PostApplyRunner $postApplyRunner,
    private readonly UpdateCommandRunner $commandRunner,
    private readonly SystemUpdateRunOutputSanitizer $sanitizer,
    private readonly RunHeartbeat $heartbeat,
    private readonly AdminUpdateIndicator $indicator,
  ) {}

  public function run(?int $userId = null): UpdateResult
  {
    // Keep the exception class resident before a post-apply Composer install
    // can remove or replace the package path that served the current request.
    class_exists(UpdateException::class);

    if (! (bool) config('publisher-client.web_run_enabled', true)) {
      throw new UpdateException('Updates are disabled for this install.');
    }

    $lock = $this->acquireLock();
    $this->armShutdownGuard();
    $this->heartbeat->beat();

    $workspace = null;
    $maintenanceEnabled = false;
    $backupHandle = null;
    $runRef = null;
    $output = [];
    $warningCount = 0;
    $startedAt = CarbonImmutable::now();
    $fromVersion = $this->versions->current();
    $toVersion = $fromVersion;

    try {
      $check = $this->client->check();
      $release = $check->release;

      if (! $check->updateAvailable || ! is_array($release)) {
        throw new UpdateException('No compatible update is available right now.');
      }

      if (($check->compatibility['status'] ?? 'unknown') === 'incompatible') {
        throw new UpdateException('This install is not compatible with the latest published release yet.');
      }

      $downloadUrl = trim((string) ($release['download_url'] ?? ''));

      if ($downloadUrl === '') {
        throw new UpdateException('The latest release does not provide a downloadable package.');
      }

      $toVersion = trim((string) ($release['version'] ?? $check->latestVersion ?? ''));

      if ($toVersion === '') {
        throw new UpdateException('The latest release metadata is incomplete and cannot be installed.');
      }

      $runRef = $this->runRecorder->start($fromVersion, $toVersion, $userId);
      $this->heartbeat->beat();
      $output[] = 'Starting update from '.$fromVersion.' to '.$toVersion.'.';

      if ((bool) config('publisher-client.backup.enabled', true)) {
        $label = sprintf('Pre-update backup before %s -> %s', $fromVersion, $toVersion);
        $backupHandle = $this->backupManager->create($label, $userId);
        $output[] = $backupHandle !== null
          ? 'Pre-update backup created.'
          : 'Pre-update backup produced no snapshot (nothing to back up).';
        $this->heartbeat->beat();
      }

      $workspace = $this->workspaceManager->create();
      $output[] = 'Workspace ready at '.$workspace['root'];

      $this->downloader->download($downloadUrl, $workspace['archive']);
      $output[] = 'Package downloaded.';
      $this->heartbeat->beat();

      $warningCount += $this->verifyChecksum($workspace['archive'], $release, $output);
      $this->signatureVerifier->verify((string) ($release['checksum_sha256'] ?? ''), $release, $output);

      $stagedRoot = $this->extractor->extract($workspace['archive'], $workspace['extract']);
      $output[] = 'Package extracted.';
      $this->heartbeat->beat();

      $commandDir = base_path();

      $this->enterMaintenance($commandDir, $output);
      $maintenanceEnabled = true;

      $applyResult = $this->applyStrategy->apply($stagedRoot);
      $warningCount += $this->mergeStrategyOutput($applyResult, $output);
      $this->heartbeat->beat();

      $this->postApplyRunner->run($commandDir, $output);
      $this->heartbeat->beat();

      $warningCount += $this->verifyAppliedVersion($toVersion, $output);

      $this->leaveMaintenance($commandDir, $output);
      $maintenanceEnabled = false;

      $this->versionStore->persist($toVersion);

      // The navbar badge is cached for an hour; the version it advertises is
      // now installed. Drop it here rather than relying on the product having
      // a cache-clearing command in its post-apply allowlist.
      $this->indicator->clear();

      $finishedAt = CarbonImmutable::now();
      $durationMs = (int) round($startedAt->diffInMilliseconds($finishedAt));
      $status = $warningCount > 0 ? RunStatus::SUCCESS_WITH_WARNINGS : RunStatus::SUCCESS;
      $summary = $warningCount > 0
        ? 'Updated to '.$toVersion.' with '.$warningCount.' warning(s).'
        : 'Updated to '.$toVersion.' successfully.';

      // Redact secrets/paths before the output is stored or shown anywhere.
      $outputText = $this->sanitizer->sanitize(implode(PHP_EOL, $output), 20000);

      $this->runRecorder->finish($runRef, $status, $summary, $outputText, $warningCount, $durationMs);
      $this->runRecorder->prune();

      Log::info('Publisher client update completed.', [
        'from_version' => $fromVersion,
        'to_version' => $toVersion,
        'status' => $status,
        'warning_count' => $warningCount,
      ]);

      return new UpdateResult(
        fromVersion: $fromVersion,
        toVersion: $toVersion,
        status: $status,
        summary: $summary,
        output: $outputText,
        warningCount: $warningCount,
        startedAt: $startedAt,
        finishedAt: $finishedAt,
        durationMs: $durationMs,
        preUpdateBackup: $backupHandle,
      );
    } catch (Throwable $throwable) {
      if ($maintenanceEnabled) {
        try {
          $this->leaveMaintenance(base_path(), $output);
          $output[] = 'Maintenance mode recovered after failure.';
        } catch (Throwable $recovery) {
          $output[] = 'Maintenance recovery failed: '.$recovery->getMessage();
        }
      }

      $failure = $throwable instanceof UpdateException
        ? $throwable
        : new UpdateException('The update failed. Review the latest update log for details.', $throwable->getMessage(), previous: $throwable);

      $status = RunStatus::FAILED;

      if ($backupHandle !== null) {
        try {
          if ($this->backupManager->restore($backupHandle)) {
            $status = RunStatus::RESTORED;
            $output[] = 'Pre-update backup restored after failure.';
          } else {
            $output[] = 'Pre-update backup restore did not complete.';
          }
        } catch (Throwable $restore) {
          $output[] = 'Pre-update backup restore failed: '.$restore->getMessage();
        }
      }

      $output[] = 'Update failed: '.$failure->getMessage();

      if ($runRef !== null) {
        $finishedAt = CarbonImmutable::now();
        $durationMs = (int) round($startedAt->diffInMilliseconds($finishedAt));
        $this->runRecorder->finish(
          $runRef,
          $status,
          $failure->userMessage(),
          $this->sanitizer->sanitize(implode(PHP_EOL, $output), 20000),
          $warningCount,
          $durationMs,
        );
        $this->runRecorder->prune();
      }

      Log::error('Publisher client update failed.', [
        'from_version' => $fromVersion,
        'to_version' => $toVersion,
        'status' => $status,
        'error' => $failure->getMessage(),
      ]);

      throw $failure;
    } finally {
      $this->workspaceManager->cleanup($workspace['root'] ?? null);
      self::$runInFlight = false;
      $this->heartbeat->clear();
      $this->releaseLock($lock);
    }
  }

  /**
   * Acquire the run lock. When the lock is held but its heartbeat says the
   * owner is dead (stale or absent — see RunHeartbeat), take it over instead
   * of rejecting: force-release and retry once. A held lock WITH a fresh
   * heartbeat is a genuinely live run and stays rejected.
   */
  private function acquireLock(): Lock
  {
    $ttl = (int) config('publisher-client.lock.ttl_seconds', 900);
    $lock = Cache::lock($this->lockName(), $ttl);

    if ($lock->get()) {
      return $lock;
    }

    if ($this->heartbeat->isStale()) {
      Log::warning('Publisher client run lock looked abandoned (no live heartbeat); taking it over.', [
        'lock' => $this->lockName(),
        'heartbeat_age_seconds' => $this->heartbeat->ageSeconds(),
        'stale_after_seconds' => $this->heartbeat->staleAfterSeconds(),
      ]);

      Cache::lock($this->lockName())->forceRelease();
      $this->heartbeat->clear();

      $lock = Cache::lock($this->lockName(), $ttl);

      if ($lock->get()) {
        return $lock;
      }
    }

    throw new UpdateException('Another update is already running. Wait for it to finish before starting a new run.');
  }

  private function armShutdownGuard(): void
  {
    self::$runInFlight = true;

    if (self::$shutdownGuardRegistered) {
      return;
    }

    self::$shutdownGuardRegistered = true;

    register_shutdown_function(function (): void {
      if (! self::$runInFlight) {
        return;
      }

      // The run never reached its finally block — the process is dying
      // fatally mid-update. Free the lock so the next attempt can run.
      try {
        Cache::lock($this->lockName())->forceRelease();
        $this->heartbeat->clear();
        Log::error('Publisher client run died fatally mid-update; run lock released by shutdown guard.', [
          'lock' => $this->lockName(),
          'last_error' => error_get_last()['message'] ?? null,
        ]);
      } catch (Throwable) {
        // Nothing more we can do while the process is dying.
      }
    });
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
    $expected = strtolower(trim((string) ($release['checksum_sha256'] ?? '')));

    if ($expected === '') {
      // Applying an update overwrites live code, so an unverifiable package is a
      // remote-code-execution risk. Refuse rather than warn (§7.8).
      throw new UpdateException(
        'The update was blocked because the release did not include a package checksum to verify against.',
        'Release metadata is missing checksum_sha256; refusing to apply an unverifiable package.',
      );
    }

    $actual = hash_file('sha256', $archivePath);

    if (! is_string($actual) || $actual === '') {
      throw new UpdateException('The downloaded package could not be verified.', 'SHA-256 checksum generation failed.');
    }

    if (! hash_equals($expected, strtolower($actual))) {
      throw new UpdateException('The downloaded package failed checksum verification.', 'Checksum mismatch for downloaded package.');
    }

    $output[] = 'Package checksum verified.';

    return 0;
  }

  private function verifyAppliedVersion(string $expected, array &$output): int
  {
    $applied = $this->versions->appliedVersion($this->applyStrategy->targetRoot());

    if ($applied === null) {
      $output[] = 'Applied version could not be read (version.const_file not configured); skipping strict verification.';

      return 1; // count as a warning
    }

    if ($this->normalize($applied) !== $this->normalize($expected)) {
      throw new UpdateException(
        'The update package was applied, but the installed version does not match the target release. The run was not marked successful.',
        'Expected version '.$expected.' after update, but the applied files report '.$applied.'.',
      );
    }

    $output[] = 'Post-update version verified as '.$expected.'.';

    return 0;
  }

  private function enterMaintenance(string $commandDir, array &$output): void
  {
    if (! (bool) config('publisher-client.apply.maintenance_mode', true)) {
      return;
    }

    $this->commandRunner->run(
      $this->commandRunner->artisanCommand(['down', '--render=errors::503']),
      $commandDir,
      $output,
    );
  }

  private function leaveMaintenance(string $commandDir, array &$output): void
  {
    if (! (bool) config('publisher-client.apply.maintenance_mode', true)) {
      return;
    }

    $this->commandRunner->run($this->commandRunner->artisanCommand(['up']), $commandDir, $output);
  }

  /**
   * @return int  warnings the strategy raised, folded into the run's warning count
   *              so a leftover-file report shows up as success-with-warnings
   *              rather than scrolling past in the log
   */
  private function mergeStrategyOutput(array $applyResult, array &$output): int
  {
    foreach ((array) ($applyResult['output'] ?? []) as $line) {
      if (is_string($line) && $line !== '') {
        $output[] = $line;
      }
    }

    return max(0, (int) ($applyResult['warnings'] ?? 0));
  }

  private function normalize(string $version): string
  {
    $version = trim($version);

    if ($version !== '' && ($version[0] === 'v' || $version[0] === 'V')) {
      $version = substr($version, 1);
    }

    return $version;
  }

  private function lockName(): string
  {
    return (string) config('publisher-client.lock.name', 'publisher-client:run');
  }

  private function releaseLock(Lock $lock): void
  {
    try {
      $lock->release();
    } catch (Throwable) {
      // Preserve the original result if lock release fails.
    }
  }
}
