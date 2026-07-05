<?php

namespace WebBlocks\Cms\Support\Translations;

use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Support\System\SystemSettings;

class AdminLocaleResolver
{
  public const SUPPORTED_LOCALES = ['en', 'de', 'tr'];

  public function __construct(
    private readonly SystemSettings $systemSettings,
  ) {}

  public function locale(): string
  {
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
      'tr' => 'TR - Turkce',
    ];
  }
}
