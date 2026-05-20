<?php

namespace WebBlocks\Cms\Support\System;

use App\Models\Locale;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Support\WebBlocks;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemSettings
{
    public const PROJECT_NAME = 'system.project_name';

    public const PROJECT_TAGLINE = 'system.project_tagline';

    public const APP_NAME = 'system.app_name';

    public const APP_SLOGAN = 'system.app_slogan';

    public const DEFAULT_LOCALE = 'system.default_locale';

    public const TIMEZONE = 'system.timezone';

    public const ADMIN_LISTING_PER_PAGE = 'admin.listing_per_page';

    public const ADMIN_LISTING_PER_PAGE_DEFAULT = 15;

    public const ADMIN_LISTING_PER_PAGE_MIN = 1;

    public const ADMIN_LISTING_PER_PAGE_MAX = 100;

    public const VISITOR_CONSENT_BANNER_ENABLED = 'system.visitor_consent_banner_enabled';

    private const READABLE_KEYS = [
        self::PROJECT_NAME,
        self::PROJECT_TAGLINE,
        self::APP_NAME,
        self::APP_SLOGAN,
        self::DEFAULT_LOCALE,
        self::TIMEZONE,
        self::ADMIN_LISTING_PER_PAGE,
        self::VISITOR_CONSENT_BANNER_ENABLED,
    ];

    public const MANAGED_KEYS = [
        self::PROJECT_NAME,
        self::PROJECT_TAGLINE,
        self::DEFAULT_LOCALE,
        self::TIMEZONE,
        self::ADMIN_LISTING_PER_PAGE,
        self::VISITOR_CONSENT_BANNER_ENABLED,
    ];

    public function all(): array
    {
        if (! $this->settingsTableExists()) {
            return [];
        }

        try {
            return SystemSetting::query()
                ->whereIn('key', self::READABLE_KEYS)
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

    public function defaultLocaleCode(): string
    {
        try {
            $defaultLocale = Locale::query()->where('is_default', true)->value('code');

            if (is_string($defaultLocale) && $defaultLocale !== '') {
                return $defaultLocale;
            }
        } catch (Throwable) {
            // Fall back to persisted settings while locale tables are unavailable.
        }

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

    public function adminListingPerPage(): int
    {
        $perPage = filter_var($this->get(self::ADMIN_LISTING_PER_PAGE), FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => self::ADMIN_LISTING_PER_PAGE_MIN,
                'max_range' => self::ADMIN_LISTING_PER_PAGE_MAX,
            ],
        ]);

        return is_int($perPage) ? $perPage : self::ADMIN_LISTING_PER_PAGE_DEFAULT;
    }

    public function projectName(): ?string
    {
        return $this->trimmed($this->get(self::PROJECT_NAME));
    }

    public function projectTagline(): ?string
    {
        return $this->trimmed($this->get(self::PROJECT_TAGLINE));
    }

    public function adminProjectIdentity(): array
    {
        $projectName = $this->projectName();
        $primarySite = $this->primarySite();
        $siteName = $this->trimmed($primarySite?->publicDisplayName())
            ?? $this->trimmed($primarySite?->name);

        return [
            'name' => $projectName
                ?? $siteName
                ?? WebBlocks::name(),
            'tagline' => $projectName !== null ? ($this->projectTagline() ?? '') : '',
        ];
    }

    public function adminBrowserTitle(?string $screenTitle = null): string
    {
        $screenTitle = $this->trimmed($screenTitle);
        $projectName = $this->projectName();
        $productName = WebBlocks::name();
        $parts = [];

        if ($projectName !== null) {
            $parts[] = $projectName;
        }

        if ($screenTitle !== null && ! in_array($screenTitle, $parts, true)) {
            $parts[] = $screenTitle;
        }

        if (! in_array($productName, $parts, true)) {
            $parts[] = $productName;
        }

        return implode(' · ', $parts);
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

        if (array_key_exists(self::DEFAULT_LOCALE, $values) && Schema::hasTable('locales')) {
            $locale = Locale::query()->where('code', Locale::normalizeCode((string) $values[self::DEFAULT_LOCALE]))->first();

            if ($locale) {
                $locale->forceFill(['is_default' => true, 'is_enabled' => true])->save();
            }
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

    private function primarySite(): ?Site
    {
        try {
            if (! Schema::hasTable('sites')) {
                return null;
            }

            return Site::query()->primaryFirst()->first();
        } catch (Throwable) {
            return null;
        }
    }

    private function trimmed(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
