<?php

namespace App\Support\System\Updates;

use App\Models\SystemUpdateRun;
use App\Models\User;
use App\Support\System\InstalledVersionStore;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemUpdater
{
    public function __construct(
        private readonly UpdateServerClient $updateServerClient,
        private readonly InstalledVersionStore $installedVersionStore,
        private readonly UpdateWorkspaceManager $workspaceManager,
        private readonly UpdatePackageDownloader $packageDownloader,
        private readonly UpdatePackageExtractor $packageExtractor,
        private readonly UpdateInstaller $updateInstaller,
    ) {}

    public function run(User $user): UpdateResult
    {
        $lock = Cache::lock($this->lockName(), (int) config('webblocks-updates.installer.lock_ttl_seconds', 900));

        if (! $lock->get()) {
            throw new UpdateException('Another update is already running. Wait for it to finish before starting a new run.');
        }

        $workspace = null;
        $maintenanceEnabled = false;
        $run = null;
        $output = [];
        $warningCount = 0;
        $startedAt = CarbonImmutable::now();
        $fromVersion = $this->installedVersionStore->currentVersion();
        $toVersion = $fromVersion;

        try {
            if (! Schema::hasTable('system_update_runs')) {
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
            $workspace = $this->workspaceManager->create();
            $output[] = 'Workspace ready at '.$workspace['root'];

            $this->packageDownloader->download($downloadUrl, $workspace['archive']);
            $output[] = 'Package downloaded to '.$workspace['archive'];

            $warningCount += $this->verifyChecksum($workspace['archive'], $release, $output);
            $packageRoot = $this->packageExtractor->extract($workspace['archive'], $workspace['extract']);
            $output[] = 'Package extracted to '.$packageRoot;

            $this->updateInstaller->enterMaintenance($output);
            $maintenanceEnabled = true;

            $this->updateInstaller->applyPackage($packageRoot, $output);
            $this->updateInstaller->installDependencies($output);
            $this->updateInstaller->runPostInstallCommands($output);
            $this->updateInstaller->leaveMaintenance($output);
            $maintenanceEnabled = false;

            $this->installedVersionStore->persist($toVersion);
            $output[] = 'Installed version persisted as '.$toVersion;

            $finishedAt = CarbonImmutable::now();
            $durationMs = $startedAt->diffInMilliseconds($finishedAt);
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
            );
        } catch (Throwable $throwable) {
            if ($maintenanceEnabled) {
                try {
                    $this->updateInstaller->leaveMaintenance($output);
                    $output[] = 'Maintenance mode recovered after failure.';
                } catch (Throwable $recoveryException) {
                    $output[] = 'Maintenance recovery failed: '.$recoveryException->getMessage();
                }
            }

            $finishedAt = CarbonImmutable::now();
            $durationMs = $startedAt->diffInMilliseconds($finishedAt);
            $failure = $throwable instanceof UpdateException
                ? $throwable
                : new UpdateException(
                    'The update failed. Review the latest update log for details.',
                    $throwable->getMessage(),
                    previous: $throwable,
                );

            $output[] = 'Update failed: '.$failure->getMessage();

            if ($run instanceof SystemUpdateRun) {
                $this->persistRun(
                    $run,
                    SystemUpdateRun::STATUS_FAILED,
                    $failure->userMessage(),
                    $output,
                    $warningCount,
                    $finishedAt,
                    $durationMs,
                );
            }

            Log::error('System update failed.', [
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
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
            $output[] = 'Package checksum was not provided by the release metadata.';

            return 1;
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
}
