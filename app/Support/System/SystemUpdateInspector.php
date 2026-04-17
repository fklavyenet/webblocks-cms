<?php

namespace App\Support\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class SystemUpdateInspector
{
    public function __construct(
        private readonly UpdateChecker $updateChecker,
        private readonly InstalledVersionStore $installedVersionStore,
    ) {}

    public function report(): array
    {
        return $this->reportFromStatus($this->updateChecker->status());
    }

    public function refreshReport(): array
    {
        return $this->reportFromStatus($this->updateChecker->refresh());
    }

    private function reportFromStatus(array $version): array
    {

        $diagnostics = [
            $this->databaseDiagnostic(),
            $this->installedVersionStore->diagnostic(),
            $this->maintenanceDiagnostic(),
            $this->cacheDiagnostic(),
        ];

        return [
            'checked_at' => $version['last_checked_at'] ?? now(),
            'version' => $version,
            'diagnostics' => $diagnostics,
            'eligibility' => $this->eligibility($version, $diagnostics),
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

    private function maintenanceDiagnostic(): array
    {
        if (app()->isDownForMaintenance()) {
            return $this->check('Maintenance mode', 'blocked', 'The application is already in maintenance mode.', true);
        }

        return $this->check('Maintenance mode', 'pass', 'The application is serving requests normally.');
    }

    private function cacheDiagnostic(): array
    {
        $probeKey = 'system_updates_cache_probe_'.str()->uuid();

        try {
            Cache::put($probeKey, 'ok', now()->addMinute());

            $matches = Cache::get($probeKey) === 'ok';
            Cache::forget($probeKey);

            if (! $matches) {
                return $this->check('Cache clear readiness', 'warning', 'The cache store did not return the probe value. cache:clear may warn in this environment.');
            }

            return $this->check('Cache clear readiness', 'pass', 'The cache store responded to a lightweight write/delete probe.');
        } catch (Throwable $throwable) {
            return $this->check('Cache clear readiness', 'warning', 'Cache probe failed: '.$throwable->getMessage());
        }
    }

    private function eligibility(array $version, array $diagnostics): array
    {
        $hasBlocked = collect($diagnostics)->contains(fn (array $check) => $check['status'] === 'blocked');
        $hasWarnings = collect($diagnostics)->contains(fn (array $check) => $check['status'] === 'warning');
        $maintenanceBlocked = collect($diagnostics)->contains(fn (array $check) => $check['label'] === 'Maintenance mode' && $check['status'] === 'blocked');

        if ($version['up_to_date']) {
            return [
                'state' => 'up_to_date',
                'label' => 'Already up to date',
                'message' => 'This install already matches the latest configured CMS version.',
                'can_update' => false,
                'badge_class' => 'wb-status-active',
            ];
        }

        if ($maintenanceBlocked) {
            return [
                'state' => 'maintenance_mode',
                'label' => 'Maintenance mode active',
                'message' => 'Bring the application back up before starting a new update run.',
                'can_update' => false,
                'badge_class' => 'wb-status-danger',
            ];
        }

        if ($hasBlocked) {
            return [
                'state' => 'blocked',
                'label' => 'Update available but blocked',
                'message' => 'Resolve the blocked diagnostics before running the update.',
                'can_update' => false,
                'badge_class' => 'wb-status-danger',
            ];
        }

        if ($hasWarnings) {
            return [
                'state' => 'warning',
                'label' => 'Diagnostics warning',
                'message' => 'The update can run, but review the warnings first.',
                'can_update' => true,
                'badge_class' => 'wb-status-pending',
            ];
        }

        return [
            'state' => 'ready',
            'label' => 'Ready to update',
            'message' => 'Diagnostics passed and the system is ready for a controlled update run.',
            'can_update' => true,
            'badge_class' => 'wb-status-info',
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
