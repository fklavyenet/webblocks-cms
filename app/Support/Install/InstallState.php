<?php

namespace App\Support\Install;

use App\Models\BlockType;
use App\Models\LayoutType;
use App\Models\Locale;
use App\Models\PageType;
use App\Models\Site;
use App\Models\SlotType;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\System\InstalledVersionStore;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class InstallState
{
    public const INSTALL_COMPLETED_AT = 'system.install_completed_at';

    public function __construct(
        private readonly EnvironmentWriter $environmentWriter,
        private readonly InstalledVersionStore $installedVersionStore,
    ) {}

    public function guardsEnabled(): bool
    {
        return (bool) config('cms.install.guard_enabled', app()->environment() !== 'testing');
    }

    public function hasAppKey(): bool
    {
        return filled((string) config('app.key'));
    }

    public function canWriteEnvironment(): bool
    {
        return $this->environmentWriter->canWrite();
    }

    public function databaseConfigured(): bool
    {
        $driver = $this->databaseConnection();

        return match ($driver) {
            'sqlite' => filled((string) config('database.connections.sqlite.database')),
            'mysql', 'mariadb', 'pgsql', 'sqlsrv' => filled((string) config("database.connections.{$driver}.host"))
                && filled((string) config("database.connections.{$driver}.database"))
                && filled((string) config("database.connections.{$driver}.username")),
            default => false,
        };
    }

    public function databaseReachable(): bool
    {
        if (! $this->databaseConfigured()) {
            return false;
        }

        try {
            DB::connection($this->databaseConnection())->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function coreInstalled(): bool
    {
        return $this->hasAppKey()
            && $this->databaseReachable()
            && $this->requiredTablesExist()
            && $this->coreSeeded();
    }

    public function firstSuperAdminExists(): bool
    {
        if (! $this->usersTableExists()) {
            return false;
        }

        try {
            return User::query()
                ->where(function ($query) {
                    $query->where('role', User::ROLE_SUPER_ADMIN)
                        ->orWhere('is_admin', true);
                })
                ->where('is_active', true)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function installMarkerExists(): bool
    {
        if (! $this->systemSettingsTableExists()) {
            return false;
        }

        try {
            return SystemSetting::query()
                ->where('key', self::INSTALL_COMPLETED_AT)
                ->whereNotNull('value')
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function isInstalled(): bool
    {
        if (! $this->coreInstalled() || ! $this->firstSuperAdminExists()) {
            return false;
        }

        return $this->installMarkerExists() || $this->legacyInstallEvidenceExists();
    }

    public function nextIncompleteStepRouteName(): string
    {
        if (! $this->databaseReachable()) {
            return 'install.database';
        }

        if (! $this->coreInstalled()) {
            return 'install.core';
        }

        if (! $this->firstSuperAdminExists()) {
            return 'install.admin';
        }

        return 'install.finish';
    }

    public function databaseConnection(): string
    {
        return (string) config('database.default', 'sqlite');
    }

    public function markInstalled(): void
    {
        if (! $this->systemSettingsTableExists()) {
            return;
        }

        SystemSetting::query()->updateOrCreate(
            ['key' => self::INSTALL_COMPLETED_AT],
            ['value' => now()->toIso8601String()],
        );
    }

    public function requirementsReport(): array
    {
        $supportedDriverCount = count(array_filter([
            extension_loaded('pdo_sqlite'),
            extension_loaded('pdo_mysql'),
            extension_loaded('pdo_pgsql'),
            extension_loaded('sqlsrv') || extension_loaded('pdo_sqlsrv'),
        ]));

        return [
            $this->check(
                'PHP version',
                version_compare(PHP_VERSION, '8.3.0', '>=') ? 'pass' : 'blocked',
                version_compare(PHP_VERSION, '8.3.0', '>=')
                    ? 'PHP '.PHP_VERSION.' meets the minimum requirement.'
                    : 'PHP 8.3 or newer is required.'
            ),
            $this->check(
                'PDO extension',
                extension_loaded('pdo') ? 'pass' : 'blocked',
                extension_loaded('pdo')
                    ? 'PDO is available.'
                    : 'The PDO extension must be enabled.'
            ),
            $this->check(
                'mbstring extension',
                extension_loaded('mbstring') ? 'pass' : 'blocked',
                extension_loaded('mbstring')
                    ? 'mbstring is available.'
                    : 'The mbstring extension must be enabled.'
            ),
            $this->check(
                'Environment file access',
                $this->canWriteEnvironment() ? 'pass' : 'blocked',
                $this->canWriteEnvironment()
                    ? '.env can be created or updated during setup.'
                    : 'The installer cannot write the environment file.'
            ),
            $this->check(
                'Runtime directories',
                $this->runtimeDirectoriesWritable() ? 'pass' : 'blocked',
                $this->runtimeDirectoriesWritable()
                    ? 'storage and bootstrap/cache are writable.'
                    : 'storage and bootstrap/cache must be writable.'
            ),
            $this->check(
                'Database driver support',
                $supportedDriverCount > 0 ? 'pass' : 'blocked',
                $supportedDriverCount > 0
                    ? 'At least one supported database driver is available.'
                    : 'No supported database driver extension is available.'
            ),
            $this->check(
                'Application key',
                $this->hasAppKey() ? 'pass' : ($this->canWriteEnvironment() ? 'warning' : 'blocked'),
                $this->hasAppKey()
                    ? 'An application key is already configured.'
                    : 'A new application key will be generated during core setup.'
            ),
        ];
    }

    public function canContinueFromRequirements(): bool
    {
        return collect($this->requirementsReport())->doesntContain(fn (array $check) => $check['blocks_continue']);
    }

    public function shouldUseRuntimeFallbacks(): bool
    {
        return ! $this->isInstalled();
    }

    private function requiredTablesExist(): bool
    {
        try {
            foreach (['users', 'sites', 'locales', 'site_locales', 'system_settings'] as $table) {
                if (! Schema::hasTable($table)) {
                    return false;
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function coreSeeded(): bool
    {
        if (! $this->requiredTablesExist()) {
            return false;
        }

        try {
            return Site::query()->exists()
                && Locale::query()->where('is_enabled', true)->exists()
                && PageType::query()->exists()
                && LayoutType::query()->exists()
                && SlotType::query()->exists()
                && BlockType::query()->exists()
                && $this->installedVersionStore->storedVersion() !== null;
        } catch (Throwable) {
            return false;
        }
    }

    private function legacyInstallEvidenceExists(): bool
    {
        return $this->installedVersionStore->storedVersion() !== null;
    }

    private function systemSettingsTableExists(): bool
    {
        try {
            return Schema::hasTable('system_settings');
        } catch (Throwable) {
            return false;
        }
    }

    private function usersTableExists(): bool
    {
        try {
            return Schema::hasTable('users');
        } catch (Throwable) {
            return false;
        }
    }

    private function runtimeDirectoriesWritable(): bool
    {
        foreach ([storage_path(), base_path('bootstrap/cache')] as $path) {
            if (! is_dir($path) || ! is_writable($path)) {
                return false;
            }
        }

        return true;
    }

    private function check(string $label, string $status, string $message): array
    {
        return [
            'label' => $label,
            'status' => $status,
            'message' => $message,
            'blocks_continue' => $status === 'blocked',
            'badge_class' => match ($status) {
                'pass' => 'wb-status-active',
                'warning' => 'wb-status-pending',
                default => 'wb-status-danger',
            },
        ];
    }
}
