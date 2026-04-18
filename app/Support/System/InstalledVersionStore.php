<?php

namespace App\Support\System;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class InstalledVersionStore
{
    public const VERSION_KEY = 'system.installed_version';

    public function currentVersion(): string
    {
        if (! Schema::hasTable('system_settings')) {
            return (string) config('webblocks-updates.client.current_version', config('app.version', '0.1.0'));
        }

        $storedVersion = SystemSetting::query()
            ->where('key', self::VERSION_KEY)
            ->value('value');

        return is_string($storedVersion) && $storedVersion !== ''
            ? $storedVersion
            : (string) config('webblocks-updates.client.current_version', config('app.version', '0.1.0'));
    }

    public function persist(string $version): void
    {
        if (! Schema::hasTable('system_settings')) {
            throw new \RuntimeException('The system settings table is missing. Run the latest migrations before updating.');
        }

        SystemSetting::query()->updateOrCreate(
            ['key' => self::VERSION_KEY],
            ['value' => $version],
        );
    }

    public function diagnostic(): array
    {
        if (! Schema::hasTable('system_settings')) {
            return $this->check('Version persistence', 'blocked', 'The system settings table is missing. Run the latest migrations before updating.', true);
        }

        $connection = DB::connection();

        try {
            $connection->getPdo();
            $connection->beginTransaction();

            $probeKey = 'system_updates.version_probe.'.str()->uuid();

            SystemSetting::query()->updateOrCreate(
                ['key' => $probeKey],
                ['value' => now()->toIso8601String()],
            );

            $value = SystemSetting::query()->where('key', $probeKey)->value('value');
            $connection->rollBack();

            if (! is_string($value) || $value === '') {
                return $this->check('Version persistence', 'blocked', 'System settings could not be read back after a write probe.', true);
            }

            return $this->check('Version persistence', 'pass', 'Installed version can be read and persisted in system settings.');
        } catch (Throwable $throwable) {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            return $this->check('Version persistence', 'blocked', 'System settings are not writable: '.$throwable->getMessage(), true);
        }
    }

    private function check(string $label, string $status, string $message, bool $blocksUpdate = false): array
    {
        return [
            'label' => $label,
            'status' => $status,
            'message' => $message,
            'blocks_update' => $blocksUpdate,
            'badge_class' => $status === 'pass' ? 'wb-status-active' : 'wb-status-danger',
        ];
    }
}
