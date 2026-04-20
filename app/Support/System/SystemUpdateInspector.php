<?php

namespace App\Support\System;

use App\Support\System\Updates\UpdateServerClient;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Throwable;

class SystemUpdateInspector
{
    public function __construct(
        private readonly UpdateServerClient $updateServerClient,
        private readonly InstalledVersionStore $installedVersionStore,
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
        ];

        return [
            'checked_at' => $version['checked_at'] ?? now(),
            'installed_version' => $installedVersion,
            'version' => $version,
            'diagnostics' => $diagnostics,
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
