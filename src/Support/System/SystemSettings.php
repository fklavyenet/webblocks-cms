<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Throwable;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\WebBlocks;

class SystemSettings
{
  public const PROJECT_NAME = 'system.project_name';

  public const PROJECT_TAGLINE = 'system.project_tagline';

  public const APP_NAME = 'system.app_name';

  public const APP_SLOGAN = 'system.app_slogan';

  public const DEFAULT_LOCALE = 'system.default_locale';

  public const TIMEZONE = 'system.timezone';

  public const ADMIN_LISTING_PER_PAGE = 'admin.listing_per_page';

  public const ADMIN_LOCALE = 'admin.locale';

  public const ADMIN_LISTING_PER_PAGE_DEFAULT = 15;

  public const ADMIN_LISTING_PER_PAGE_MIN = 1;

  public const ADMIN_LISTING_PER_PAGE_MAX = 100;

  public const VISITOR_CONSENT_BANNER_ENABLED = 'system.visitor_consent_banner_enabled';

  public const CMS_MAIL_MODE = 'system.cms_mail_mode';

  public const CMS_MAIL_MAILER = 'system.cms_mail_mailer';

  public const CMS_MAIL_HOST = 'system.cms_mail_host';

  public const CMS_MAIL_PORT = 'system.cms_mail_port';

  public const CMS_MAIL_ENCRYPTION = 'system.cms_mail_encryption';

  public const CMS_MAIL_USERNAME = 'system.cms_mail_username';

  public const CMS_MAIL_PASSWORD = 'system.cms_mail_password';

  public const CMS_MAIL_FROM_ADDRESS = 'system.cms_mail_from_address';

  public const CMS_MAIL_FROM_NAME = 'system.cms_mail_from_name';

  public const CMS_MAIL_REPLY_TO_ADDRESS = 'system.cms_mail_reply_to_address';

  public const CMS_MAIL_TIMEOUT = 'system.cms_mail_timeout';

  public const CMS_MAIL_MODE_ENV = 'env';

  public const CMS_MAIL_MODE_CUSTOM = 'custom';

  public const BACKUP_CLEANUP_ENABLED = 'system.backup_cleanup_enabled';

  public const BACKUP_CLEANUP_PRE_UPDATE_DAYS = 'system.backup_cleanup_pre_update_days';

  public const BACKUP_CLEANUP_KEEP_LATEST_PRE_UPDATE = 'system.backup_cleanup_keep_latest_pre_update';

  public const BACKUP_CLEANUP_RESTORE_SAFETY_DAYS = 'system.backup_cleanup_restore_safety_days';

  public const BACKUP_CLEANUP_CONTENT_APPLY_DAYS = 'system.backup_cleanup_content_apply_days';

  public const CLEANUP_ASSET_REVISION_DAYS = 'system.cleanup_asset_revision_days';

  public const CLEANUP_KEEP_LATEST_ASSET_REVISIONS = 'system.cleanup_keep_latest_asset_revisions';

  public const CLEANUP_TEMPORARY_WORKSPACE_HOURS = 'system.cleanup_temporary_workspace_hours';

  private const READABLE_KEYS = [
    self::PROJECT_NAME,
    self::PROJECT_TAGLINE,
    self::APP_NAME,
    self::APP_SLOGAN,
    self::DEFAULT_LOCALE,
    self::TIMEZONE,
    self::ADMIN_LISTING_PER_PAGE,
    self::ADMIN_LOCALE,
    self::VISITOR_CONSENT_BANNER_ENABLED,
    self::CMS_MAIL_MODE,
    self::CMS_MAIL_MAILER,
    self::CMS_MAIL_HOST,
    self::CMS_MAIL_PORT,
    self::CMS_MAIL_ENCRYPTION,
    self::CMS_MAIL_USERNAME,
    self::CMS_MAIL_PASSWORD,
    self::CMS_MAIL_FROM_ADDRESS,
    self::CMS_MAIL_FROM_NAME,
    self::CMS_MAIL_REPLY_TO_ADDRESS,
    self::CMS_MAIL_TIMEOUT,
    self::BACKUP_CLEANUP_ENABLED,
    self::BACKUP_CLEANUP_PRE_UPDATE_DAYS,
    self::BACKUP_CLEANUP_KEEP_LATEST_PRE_UPDATE,
    self::BACKUP_CLEANUP_RESTORE_SAFETY_DAYS,
    self::BACKUP_CLEANUP_CONTENT_APPLY_DAYS,
    self::CLEANUP_ASSET_REVISION_DAYS,
    self::CLEANUP_KEEP_LATEST_ASSET_REVISIONS,
    self::CLEANUP_TEMPORARY_WORKSPACE_HOURS,
  ];

  public const MANAGED_KEYS = [
    self::PROJECT_NAME,
    self::PROJECT_TAGLINE,
    self::DEFAULT_LOCALE,
    self::TIMEZONE,
    self::ADMIN_LISTING_PER_PAGE,
    self::ADMIN_LOCALE,
    self::VISITOR_CONSENT_BANNER_ENABLED,
    self::CMS_MAIL_MODE,
    self::CMS_MAIL_MAILER,
    self::CMS_MAIL_HOST,
    self::CMS_MAIL_PORT,
    self::CMS_MAIL_ENCRYPTION,
    self::CMS_MAIL_USERNAME,
    self::CMS_MAIL_PASSWORD,
    self::CMS_MAIL_FROM_ADDRESS,
    self::CMS_MAIL_FROM_NAME,
    self::CMS_MAIL_REPLY_TO_ADDRESS,
    self::CMS_MAIL_TIMEOUT,
    self::BACKUP_CLEANUP_ENABLED,
    self::BACKUP_CLEANUP_PRE_UPDATE_DAYS,
    self::BACKUP_CLEANUP_KEEP_LATEST_PRE_UPDATE,
    self::BACKUP_CLEANUP_RESTORE_SAFETY_DAYS,
    self::BACKUP_CLEANUP_CONTENT_APPLY_DAYS,
    self::CLEANUP_ASSET_REVISION_DAYS,
    self::CLEANUP_KEEP_LATEST_ASSET_REVISIONS,
    self::CLEANUP_TEMPORARY_WORKSPACE_HOURS,
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

  public function adminLocale(): string
  {
    $locale = Locale::normalizeCode((string) $this->get(self::ADMIN_LOCALE, ''));

    return $locale !== '' ? $locale : Locale::normalizeCode((string) config('app.locale', 'en'));
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
    $productName = WebBlocks::name();
    $screenTitle = $this->normalizedAdminScreenTitle($screenTitle, $productName);

    if ($screenTitle === null || $screenTitle === $productName) {
      return $productName;
    }

    if (str_contains($screenTitle, $productName)) {
      return $screenTitle;
    }

    return $screenTitle.' - '.$productName;
  }

  private function normalizedAdminScreenTitle(?string $screenTitle, string $productName): ?string
  {
    $screenTitle = $this->trimmed($screenTitle);

    if ($screenTitle === null) {
      return null;
    }

    $screenTitle = preg_replace('/\s+/', ' ', $screenTitle) ?: $screenTitle;

    if ($screenTitle === 'Admin Dashboard') {
      return 'Dashboard';
    }

    foreach ([' - ', ' · '] as $separator) {
      $suffix = $separator.$productName;

      if (str_ends_with($screenTitle, $suffix)) {
        return $this->trimmed(substr($screenTitle, 0, -strlen($suffix))) ?? $productName;
      }
    }

    return $screenTitle;
  }

  public function visitorConsentBannerEnabled(): bool
  {
    $stored = $this->get(self::VISITOR_CONSENT_BANNER_ENABLED);

    if ($stored === null || $stored === '') {
      return (bool) config('cms.visitor_reports.consent_banner_enabled', true);
    }

    return filter_var($stored, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
  }

  public function cmsMailMode(): string
  {
    $mode = trim((string) $this->get(self::CMS_MAIL_MODE, self::CMS_MAIL_MODE_ENV));

    return $mode === self::CMS_MAIL_MODE_CUSTOM ? self::CMS_MAIL_MODE_CUSTOM : self::CMS_MAIL_MODE_ENV;
  }

  public function cmsMailSettings(): array
  {
    return [
      'mode' => $this->cmsMailMode(),
      'mailer' => $this->trimmed($this->get(self::CMS_MAIL_MAILER)) ?? 'smtp',
      'host' => $this->trimmed($this->get(self::CMS_MAIL_HOST)),
      'port' => $this->trimmed($this->get(self::CMS_MAIL_PORT)),
      'encryption' => $this->trimmed($this->get(self::CMS_MAIL_ENCRYPTION)),
      'username' => $this->trimmed($this->get(self::CMS_MAIL_USERNAME)),
      'password' => (string) ($this->get(self::CMS_MAIL_PASSWORD) ?? ''),
      'from_address' => $this->trimmed($this->get(self::CMS_MAIL_FROM_ADDRESS)),
      'from_name' => $this->trimmed($this->get(self::CMS_MAIL_FROM_NAME)),
      'reply_to_address' => $this->trimmed($this->get(self::CMS_MAIL_REPLY_TO_ADDRESS)),
      'timeout' => $this->trimmed($this->get(self::CMS_MAIL_TIMEOUT)),
    ];
  }

  public function cmsMailPasswordConfigured(): bool
  {
    return $this->trimmed($this->get(self::CMS_MAIL_PASSWORD)) !== null;
  }

  public function backupCleanupEnabled(): bool
  {
    return filter_var($this->get(self::BACKUP_CLEANUP_ENABLED, true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
  }

  /** @return array{enabled: bool, pre_update_days: int, keep_latest_pre_update: int, restore_safety_days: int, content_apply_days: int} */
  public function backupCleanupSettings(): array
  {
    return [
      'enabled' => $this->backupCleanupEnabled(),
      'pre_update_days' => $this->boundedInt(self::BACKUP_CLEANUP_PRE_UPDATE_DAYS, 14, 1, 3650),
      'keep_latest_pre_update' => $this->boundedInt(self::BACKUP_CLEANUP_KEEP_LATEST_PRE_UPDATE, 5, 1, 100),
      'restore_safety_days' => $this->boundedInt(self::BACKUP_CLEANUP_RESTORE_SAFETY_DAYS, 30, 1, 3650),
      'content_apply_days' => $this->boundedInt(self::BACKUP_CLEANUP_CONTENT_APPLY_DAYS, 7, 1, 3650),
    ];
  }

  /** @return array{asset_revision_days: int, keep_latest_asset_revisions: int, temporary_workspace_hours: int} */
  public function maintenanceCleanupSettings(): array
  {
    return [
      'asset_revision_days' => $this->boundedInt(self::CLEANUP_ASSET_REVISION_DAYS, 90, 1, 3650),
      'keep_latest_asset_revisions' => $this->boundedInt(self::CLEANUP_KEEP_LATEST_ASSET_REVISIONS, 20, 1, 1000),
      'temporary_workspace_hours' => $this->boundedInt(self::CLEANUP_TEMPORARY_WORKSPACE_HOURS, 24, 1, 8760),
    ];
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

    if (array_key_exists(self::DEFAULT_LOCALE, $values) && Schema::hasTable('wbcms_locales')) {
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
      return Schema::hasTable('wbcms_system_settings');
    } catch (Throwable) {
      return false;
    }
  }

  private function primarySite(): ?Site
  {
    try {
      if (! Schema::hasTable('wbcms_sites')) {
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

  private function boundedInt(string $key, int $default, int $min, int $max): int
  {
    $value = filter_var($this->get($key), FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);

    return is_int($value) ? $value : $default;
  }
}
