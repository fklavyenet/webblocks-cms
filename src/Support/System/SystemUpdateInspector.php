<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Throwable;
use WebBlocks\Cms\Support\System\Updates\SystemUpdater;
use WebBlocks\Cms\Support\System\Updates\UpdateServerClient;
use ZipArchive;

/**
 * Preflight inspector for the one-click self-update flow. Runs the environment
 * checks the updater depends on and reports a single `can_update` verdict:
 * an update is offered when a newer release with a package URL is available,
 * every preflight check passes, and no other update run holds the lock.
 */
class SystemUpdateInspector
{
  private const MINIMUM_FREE_DISK_BYTES = 500 * 1024 * 1024;

  public function __construct(
    private readonly UpdateServerClient $updateServerClient,
    private readonly InstalledVersionStore $installedVersionStore,
    private readonly SystemUpdater $systemUpdater,
  ) {}

  public function report(): array
  {
    return $this->reportFromStatus($this->updateServerClient->check()->toArray());
  }

  public function refreshReport(): array
  {
    return $this->report();
  }

  private function reportFromStatus(array $version): array
  {
    $installedVersion = $this->installedVersionStore->currentVersion();
    $version['installed_version'] = $installedVersion;
    $latestVersion = $version['latest_version'] ?? null;

    if (is_string($latestVersion) && $latestVersion !== '' && version_compare($latestVersion, $installedVersion, '<=')) {
      $currentCodeIsNewerThanPublished = version_compare($installedVersion, $latestVersion, '>');

      $version['state'] = 'up_to_date';
      $version['label'] = $currentCodeIsNewerThanPublished
        ? 'Local/source version is newer'
        : 'Already up to date';
      $version['message'] = $currentCodeIsNewerThanPublished
        ? 'This install is newer than the latest published release for the selected channel.'
        : 'This install is already on the latest published release.';
      $version['badge_class'] = 'wb-status-active';
      $version['update_available'] = false;
    }

    $checks = [
      $this->databaseCheck(),
      $this->archiveSupportCheck(),
      $this->signatureSupportCheck(),
      $this->commandExecutionCheck(),
      $this->writablePathCheck(
        label: 'Application root write access',
        path: (string) config('webblocks-updates.installer.target_path', base_path()),
        missingMessage: 'The configured application root for updates does not exist.',
      ),
      $this->writablePathCheck(
        label: 'Update workspace',
        path: storage_path(trim((string) config('webblocks-updates.installer.workspace_root', 'app/system-updates'), '/')),
        missingMessage: 'The update workspace directory could not be created.',
        createIfMissing: true,
      ),
      $this->freeDiskSpaceCheck(),
    ];

    $checksPass = collect($checks)->every(fn (array $check): bool => $check['status'] === 'pass');

    $updateAvailable = ($version['update_available'] ?? false) === true
      && ($version['compatibility']['status'] ?? 'unknown') !== 'incompatible';
    $downloadUrl = trim((string) ($version['release']['download_url'] ?? ''));

    $canUpdate = $updateAvailable
      && $downloadUrl !== ''
      && $checksPass
      && ! $this->systemUpdater->isLocked();

    return [
      'checked_at' => $version['checked_at'] ?? now(),
      'installed_version' => $installedVersion,
      'stored_installed_version' => $this->installedVersionStore->storedVersion(),
      'version' => $version,
      'checks' => $checks,
      'can_update' => $canUpdate,
      'environment' => [
        'server_url' => $version['server_url'] ?? '',
        'product' => $version['product'] ?? config('webblocks-updates.product', 'webblocks-cms'),
        'channel' => $version['channel'] ?? config('webblocks-updates.channel', 'stable'),
        'php_version' => PHP_VERSION,
        'laravel_version' => Application::VERSION,
        'site_url' => (string) config('webblocks-updates.site_url', config('app.url')),
      ],
    ];
  }

  private function databaseCheck(): array
  {
    try {
      DB::connection()->getPdo();
    } catch (Throwable $throwable) {
      return $this->check('Database connection', 'fail', 'The database connection failed: '.$throwable->getMessage());
    }

    if (! Schema::hasTable('wbcms_system_update_runs')) {
      return $this->check('Database connection', 'fail', 'The system update runs table is missing. Run the latest migrations before updating.');
    }

    return $this->check('Database connection', 'pass', 'The database is reachable and update runs can be recorded.');
  }

  private function archiveSupportCheck(): array
  {
    if (! class_exists(ZipArchive::class)) {
      return $this->check('Archive extraction', 'fail', 'The PHP ZIP extension is missing, so update packages cannot be extracted.');
    }

    return $this->check('Archive extraction', 'pass', 'The PHP ZIP extension is available for package extraction.');
  }

  private function signatureSupportCheck(): array
  {
    if (! function_exists('sodium_crypto_sign_verify_detached')) {
      return $this->check('Release signature verification', 'fail', 'The PHP sodium extension is missing, so signed releases cannot be verified.');
    }

    return $this->check('Release signature verification', 'pass', 'The PHP sodium extension is available for Ed25519 release verification.');
  }

  private function commandExecutionCheck(): array
  {
    if (! function_exists('proc_open')) {
      return $this->check('Command execution', 'fail', 'PHP process execution is disabled, so maintenance and migration commands cannot run.');
    }

    $phpBinary = (new PhpExecutableFinder)->find(false);

    if (! is_string($phpBinary) || $phpBinary === '') {
      return $this->check('Command execution', 'fail', 'The PHP CLI binary could not be resolved, so update commands cannot run.');
    }

    $composerBinary = (new ExecutableFinder)->find('composer');

    if (! is_string($composerBinary) || $composerBinary === '') {
      return $this->check('Command execution', 'fail', 'Composer is not available on the server, so package autoload metadata cannot be rebuilt.');
    }

    return $this->check('Command execution', 'pass', 'PHP, Composer, and process execution are available for update commands.');
  }

  private function freeDiskSpaceCheck(): array
  {
    $workspaceRoot = storage_path();

    try {
      $freeBytes = @disk_free_space($workspaceRoot);
    } catch (Throwable) {
      $freeBytes = false;
    }

    if (! is_float($freeBytes) && ! is_int($freeBytes)) {
      return $this->check('Free disk space', 'fail', 'Free disk space could not be determined for '.$workspaceRoot.'.');
    }

    if ($freeBytes < self::MINIMUM_FREE_DISK_BYTES) {
      return $this->check(
        'Free disk space',
        'fail',
        sprintf('Only %.0f MB free under %s; at least %d MB is required for backup and update workspaces.', $freeBytes / 1048576, $workspaceRoot, self::MINIMUM_FREE_DISK_BYTES / 1048576),
      );
    }

    return $this->check('Free disk space', 'pass', sprintf('%.0f MB free for backup and update workspaces.', $freeBytes / 1048576));
  }

  private function writablePathCheck(string $label, string $path, string $missingMessage, bool $createIfMissing = false): array
  {
    try {
      if (! File::exists($path)) {
        if (! $createIfMissing) {
          return $this->check($label, 'fail', $missingMessage);
        }

        File::ensureDirectoryExists($path);
      }

      if (! File::isDirectory($path)) {
        return $this->check($label, 'fail', 'The configured path is not a directory: '.$path);
      }

      $probeDirectory = $path.'/.wb-update-probe-'.str()->uuid();
      File::ensureDirectoryExists($probeDirectory);
      File::deleteDirectory($probeDirectory);

      return $this->check($label, 'pass', 'Write access to '.$path.' is available for automatic updates.');
    } catch (Throwable $throwable) {
      return $this->check($label, 'fail', 'Write access check failed: '.$throwable->getMessage());
    }
  }

  private function check(string $label, string $status, string $message): array
  {
    return [
      'label' => $label,
      'status' => $status,
      'message' => $message,
      'badge_class' => $status === 'pass' ? 'wb-status-active' : 'wb-status-danger',
    ];
  }
}
