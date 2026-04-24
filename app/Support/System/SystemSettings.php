<?php

namespace App\Support\System;

use App\Models\Locale;
use App\Models\SystemSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemSettings
{
    public const APP_NAME = 'system.app_name';
    public const APP_SLOGAN = 'system.app_slogan';
    public const DEFAULT_LOCALE = 'system.default_locale';
    public const TIMEZONE = 'system.timezone';
    public const VISITOR_CONSENT_BANNER_ENABLED = 'system.visitor_consent_banner_enabled';

    public const MANAGED_KEYS = [
        self::APP_NAME,
        self::APP_SLOGAN,
        self::DEFAULT_LOCALE,
        self::TIMEZONE,
        self::VISITOR_CONSENT_BANNER_ENABLED,
    ];

    public function all(): array
    {
        if (! $this->settingsTableExists()) {
            return [];
        }

        try {
            return SystemSetting::query()
                ->whereIn('key', self::MANAGED_KEYS)
                ->pluck('value', 'key')
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default);
    }

    public function appName(): string
    {
        $value = trim((string) $this->get(self::APP_NAME, ''));

        return $value !== '' ? $value : (string) config('app.name');
    }

    public function appSlogan(): string
    {
        $value = trim((string) $this->get(self::APP_SLOGAN, ''));

        return $value !== '' ? $value : (string) config('app.slogan');
    }

    public function defaultLocaleCode(): string
    {
        $configured = Locale::normalizeCode((string) $this->get(self::DEFAULT_LOCALE, ''));

        if ($configured) {
            return $configured;
        }

        $fallback = Locale::normalizeCode((string) config('app.locale'));

        if ($fallback) {
            return $fallback;
        }

        try {
            return Locale::query()->where('is_default', true)->value('code') ?? 'en';
        } catch (Throwable) {
            return 'en';
        }
    }

    public function timezone(): string
    {
        $timezone = trim((string) $this->get(self::TIMEZONE, ''));

        return $timezone !== '' ? $timezone : (string) config('app.timezone', 'UTC');
    }

    public function visitorConsentBannerEnabled(): bool
    {
        $stored = $this->get(self::VISITOR_CONSENT_BANNER_ENABLED);

        if ($stored === null || $stored === '') {
            return (bool) config('cms.visitor_reports.consent_banner_enabled', true);
        }

        return filter_var($stored, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }

    public function save(array $values): void
    {
        if (! $this->settingsTableExists()) {
            throw new \RuntimeException('The system settings table is missing. Run the latest migrations before saving settings.');
        }

        foreach (self::MANAGED_KEYS as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $value = $values[$key];
            $stored = is_string($value) ? trim($value) : $value;

            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $stored === '' ? null : $stored],
            );
        }
    }

    public function timezoneOptions(): array
    {
        return collect(\DateTimeZone::listIdentifiers())
            ->mapWithKeys(fn (string $timezone) => [$timezone => $timezone])
            ->all();
    }

    public function enabledLocaleOptions(): array
    {
        try {
            return Locale::query()
                ->where('is_enabled', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Locale $locale) => [
                    $locale->code => strtoupper($locale->code).' - '.$locale->name.($locale->is_default ? ' (Default)' : ''),
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function settingsTableExists(): bool
    {
        try {
            return Schema::hasTable('system_settings');
        } catch (Throwable) {
            return false;
        }
    }
}
