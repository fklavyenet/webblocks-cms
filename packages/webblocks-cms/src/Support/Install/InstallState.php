<?php

namespace WebBlocks\Cms\Support\Install;

use Illuminate\Support\Facades\Schema;
use Throwable;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\LayoutType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\PageType;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\System\InstalledVersionStore;

class InstallState
{
    public const INSTALL_COMPLETED_AT = 'system.install_completed_at';

    public function __construct(
        private readonly InstalledVersionStore $installedVersionStore,
    ) {}

    public function guardsEnabled(): bool
    {
        return (bool) config('cms.install.guard_enabled', app()->environment() !== 'testing');
    }

    public function coreInstalled(): bool
    {
        return $this->requiredTablesExist() && $this->coreSeeded();
    }

    public function firstSuperAdminExists(): bool
    {
        if (! Schema::hasTable('users')) {
            return false;
        }

        try {
            $userModel = config('auth.providers.users.model', 'App\\Models\\User');

            if (! is_string($userModel) || ! class_exists($userModel)) {
                return false;
            }

            return $userModel::query()
                ->where('role', 'super_admin')
                ->where('is_active', true)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function installMarkerExists(): bool
    {
        if (! Schema::hasTable('system_settings')) {
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
        return $this->coreInstalled()
            && $this->firstSuperAdminExists()
            && ($this->installMarkerExists() || $this->installedVersionStore->storedVersion() !== null);
    }

    public function markInstalled(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        SystemSetting::query()->updateOrCreate(
            ['key' => self::INSTALL_COMPLETED_AT],
            ['value' => now()->toIso8601String()],
        );
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
}
