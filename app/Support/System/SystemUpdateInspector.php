<?php

namespace App\Support\System;

use App\Support\System\Updates\SystemUpdater;
use App\Support\System\Updates\UpdateServerClient;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;
use ZipArchive;

class SystemUpdateInspector
{
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

        $diagnostics = [
            $this->databaseDiagnostic(),
            $this->installedVersionStore->diagnostic(),
            $this->updateServerDiagnostic($version),
            $this->updateRunLoggingDiagnostic(),
            $this->archiveSupportDiagnostic(),
            $this->commandExecutionDiagnostic(),
            $this->composerDiagnostic(),
            $this->targetPathDiagnostic(),
            $this->workspaceDiagnostic(),
        ];

        $autoUpdate = $this->autoUpdateState($version, $diagnostics);

        return [
            'checked_at' => $version['checked_at'] ?? now(),
            'installed_version' => $installedVersion,
            'version' => $version,
            'diagnostics' => $diagnostics,
            'auto_update' => $autoUpdate,
            'environment' => [
                'server_url' => $version['server_url'] ?? '',
                'product' => $version['product'] ?? config('webblocks-updates.client.product', 'webblocks-cms'),
                'channel' => $version['channel'] ?? config('webblocks-updates.client.channel', 'stable'),
                'php_version' => PHP_VERSION,
                'laravel_version' => Application::VERSION,
                'site_url' => (string) config('webblocks-updates.client.site_url', config('app.url')),
            ],
        ];
    }

    private function databaseDiagnostic(): array
    {
        try {
            DB::connection()->getPdo();

            return $this->check('Database connection', 'pass', 'The database is reachable and ready for update commands.');
        } catch (Throwable $throwable) {
            return $this->check('Database connection', 'blocked', 'The database connection failed: '.$throwable->getMessage(), true);
        }
    }

    private function updateServerDiagnostic(array $version): array
    {
        if (($version['server_reachable'] ?? false) === true) {
            return $this->check('Update server connectivity', 'pass', 'The configured update server responded to the latest check.');
        }

        return $this->check('Update server connectivity', 'warning', $version['message'] ?? 'The update server could not be reached.');
    }

    private function updateRunLoggingDiagnostic(): array
    {
        if (! Schema::hasTable('system_update_runs')) {
            return $this->check('Update run logging', 'blocked', 'The system update runs table is missing. Run the latest migrations before updating.', true);
        }

        return $this->check('Update run logging', 'pass', 'Automatic update runs can be recorded in the database.');
    }

    private function archiveSupportDiagnostic(): array
    {
        if (! class_exists(ZipArchive::class)) {
            return $this->check('Archive extraction', 'blocked', 'The PHP ZIP extension is missing, so update packages cannot be extracted.', true);
        }

        return $this->check('Archive extraction', 'pass', 'The PHP ZIP extension is available for package extraction.');
    }

    private function commandExecutionDiagnostic(): array
    {
        if (! function_exists('proc_open')) {
            return $this->check('Command execution', 'blocked', 'PHP process execution is disabled, so maintenance and migration commands cannot run.', true);
        }

        return $this->check('Command execution', 'pass', 'PHP process execution is available for maintenance and migration commands.');
    }

    private function composerDiagnostic(): array
    {
        $composer = (new ExecutableFinder)->find('composer');

        if (! is_string($composer) || $composer === '') {
            return $this->check('Composer availability', 'blocked', 'Composer is not available on the server, so source package dependencies cannot be installed.', true);
        }

        return $this->check('Composer availability', 'pass', 'Composer is available for post-package dependency installation.');
    }

    private function targetPathDiagnostic(): array
    {
        return $this->writablePathDiagnostic(
            label: 'Application root write access',
            path: (string) config('webblocks-updates.installer.target_path', base_path()),
            missingMessage: 'The configured application root for updates does not exist.',
        );
    }

    private function workspaceDiagnostic(): array
    {
        $workspacePath = storage_path(trim((string) config('webblocks-updates.installer.workspace_root', 'app/system-updates'), '/'));

        return $this->writablePathDiagnostic(
            label: 'Update workspace',
            path: $workspacePath,
            missingMessage: 'The update workspace directory could not be created.',
            createIfMissing: true,
        );
    }

    private function writablePathDiagnostic(string $label, string $path, string $missingMessage, bool $createIfMissing = false): array
    {
        try {
            if (! File::exists($path)) {
                if (! $createIfMissing) {
                    return $this->check($label, 'blocked', $missingMessage, true);
                }

                File::ensureDirectoryExists($path);
            }

            if (! File::isDirectory($path)) {
                return $this->check($label, 'blocked', 'The configured path is not a directory: '.$path, true);
            }

            $probeDirectory = $path.'/.wb-update-probe-'.str()->uuid();
            File::ensureDirectoryExists($probeDirectory);
            File::deleteDirectory($probeDirectory);

            return $this->check($label, 'pass', 'Write access to '.$path.' is available for automatic updates.');
        } catch (Throwable $throwable) {
            return $this->check($label, 'blocked', 'Write access check failed: '.$throwable->getMessage(), true);
        }
    }

    private function autoUpdateState(array $version, array $diagnostics): array
    {
        $blockers = [];

        foreach ($diagnostics as $diagnostic) {
            if (($diagnostic['blocks_update'] ?? false) === true) {
                $blockers[] = $diagnostic['message'];
            }
        }

        if ($this->systemUpdater->isLocked()) {
            $blockers[] = 'Another update run is already in progress.';
        }

        if (($version['state'] ?? null) === 'incompatible') {
            foreach (($version['compatibility']['reasons'] ?? []) as $reason) {
                $blockers[] = $reason;
            }
        }

        if (($version['update_available'] ?? false) !== true) {
            $blockers[] = 'No newer release is ready for this install.';
        }

        if (! is_string($version['release']['download_url'] ?? null) || trim((string) $version['release']['download_url']) === '') {
            $blockers[] = 'The latest release does not provide an installable package URL.';
        }

        $blockers = array_values(array_unique(array_filter($blockers, static fn ($message): bool => is_string($message) && $message !== '')));

        return [
            'allowed' => $blockers === [] && ($version['state'] ?? null) === 'update_available',
            'blockers' => $blockers,
            'busy' => in_array('Another update run is already in progress.', $blockers, true),
        ];
    }

    private function check(string $label, string $status, string $message, bool $blocksUpdate = false): array
    {
        return [
            'label' => $label,
            'status' => $status,
            'message' => $message,
            'blocks_update' => $blocksUpdate,
            'badge_class' => match ($status) {
                'pass' => 'wb-status-active',
                'warning' => 'wb-status-pending',
                default => 'wb-status-danger',
            },
        ];
    }
}
