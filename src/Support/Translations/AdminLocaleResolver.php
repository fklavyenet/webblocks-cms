<?php

namespace WebBlocks\Cms\Support\Translations;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Support\System\SystemSettings;

class AdminLocaleResolver
{
  public const SUPPORTED_LOCALES = ['en', 'de', 'tr', 'es', 'it'];

  public function __construct(
    private readonly SystemSettings $systemSettings,
  ) {}

  public function locale(?Authenticatable $user = null): string
  {
    $userLocale = $this->userLocale($user ?? request()?->user());

    if ($userLocale !== null) {
      return $userLocale;
    }

    $configured = Locale::normalizeCode((string) $this->systemSettings->get(SystemSettings::ADMIN_LOCALE, ''));

    if ($configured !== '' && in_array($configured, self::SUPPORTED_LOCALES, true)) {
      return $configured;
    }

    $appLocale = Locale::normalizeCode((string) config('app.locale', 'en'));

    if ($appLocale !== '' && in_array($appLocale, self::SUPPORTED_LOCALES, true)) {
      return $appLocale;
    }

    return 'en';
  }

  public function options(): array
  {
    return [
      'en' => 'EN - English',
      'de' => 'DE - Deutsch',
      'tr' => 'TR - Türkçe',
      'es' => 'ES - Español',
      'it' => 'IT - Italiano',
    ];
  }

  public function userPreferencesAvailable(): bool
  {
    try {
      return Schema::hasTable('users') && Schema::hasColumn('users', 'admin_locale');
    } catch (\Throwable) {
      return false;
    }
  }

  private function userLocale(?Authenticatable $user): ?string
  {
    if (! $user || ! $this->userPreferencesAvailable()) {
      return null;
    }

    $locale = Locale::normalizeCode((string) ($user->getAuthIdentifier() ? $user->getAttribute('admin_locale') : ''));

    return $locale !== '' && in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : null;
  }
}
