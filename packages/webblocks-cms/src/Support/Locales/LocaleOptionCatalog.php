<?php

namespace WebBlocks\Cms\Support\Locales;

use ResourceBundle;
use WebBlocks\Cms\Models\Locale;

class LocaleOptionCatalog
{
  private const COMMON_CODES = [
    'en',
    'en-us',
    'en-gb',
    'de',
    'de-de',
    'tr',
    'tr-tr',
    'fr',
    'fr-fr',
    'es',
    'es-es',
    'it',
    'it-it',
    'nl',
    'nl-nl',
    'pt',
    'pt-br',
    'ar',
    'ru',
    'zh',
    'zh-hans',
    'zh-hant',
    'ja',
    'ko',
  ];

  private const FALLBACK_NAMES = [
    'ar' => 'Arabic',
    'de' => 'German',
    'de-de' => 'German (Germany)',
    'en' => 'English',
    'en-gb' => 'English (United Kingdom)',
    'en-us' => 'English (United States)',
    'es' => 'Spanish',
    'es-es' => 'Spanish (Spain)',
    'fr' => 'French',
    'fr-fr' => 'French (France)',
    'it' => 'Italian',
    'it-it' => 'Italian (Italy)',
    'ja' => 'Japanese',
    'ko' => 'Korean',
    'nl' => 'Dutch',
    'nl-nl' => 'Dutch (Netherlands)',
    'pt' => 'Portuguese',
    'pt-br' => 'Portuguese (Brazil)',
    'ru' => 'Russian',
    'tr' => 'Turkish',
    'tr-tr' => 'Turkish (Turkey)',
    'zh' => 'Chinese',
    'zh-hans' => 'Chinese (Simplified)',
    'zh-hant' => 'Chinese (Traditional)',
  ];

  public function groupedOptions(array $installedCodes = []): array
  {
    $options = $this->options($installedCodes);
    $commonCodes = array_flip(self::COMMON_CODES);

    return [
      'common' => array_values(array_filter($options, fn (array $option): bool => isset($commonCodes[$option['code']]))),
      'all' => $options,
    ];
  }

  public function options(array $installedCodes = []): array
  {
    $installed = collect($installedCodes)
      ->map(fn (string $code): ?string => Locale::normalizeCode($code))
      ->filter()
      ->flip();

    return collect($this->catalogCodes())
      ->map(fn (string $code): array => $this->option($code, $installed->has($code)))
      ->sortBy([
        ['english_name', 'asc'],
        ['code', 'asc'],
      ])
      ->values()
      ->all();
  }

  public function find(?string $code): ?array
  {
    $code = Locale::normalizeCode($code);

    if (! Locale::isValidCode($code)) {
      return null;
    }

    return collect($this->options())
      ->firstWhere('code', $code);
  }

  private function catalogCodes(): array
  {
    $codes = self::COMMON_CODES;

    if (class_exists(ResourceBundle::class)) {
      $codes = [
        ...$codes,
        ...array_map(
          fn (string $locale): ?string => Locale::normalizeCode($locale),
          ResourceBundle::getLocales('') ?: [],
        ),
      ];
    }

    return collect($codes)
      ->filter(fn (?string $code): bool => Locale::isValidCode($code))
      ->unique()
      ->values()
      ->all();
  }

  private function option(string $code, bool $installed): array
  {
    $englishName = $this->displayName($code, 'en');
    $nativeName = $this->displayName($code, $this->icuCode($code));
    $name = $englishName ?: (self::FALLBACK_NAMES[$code] ?? $code);
    $nativeName = $nativeName ?: $name;
    $label = $nativeName === $name
      ? "{$name} ({$code})"
      : "{$nativeName} - {$name} ({$code})";

    return [
      'code' => $code,
      'name' => $name,
      'native_name' => $nativeName,
      'english_name' => $name,
      'label' => $label,
      'is_common' => in_array($code, self::COMMON_CODES, true),
      'installed' => $installed,
      'search' => strtolower($code.' '.$name.' '.$nativeName.' '.$label),
    ];
  }

  private function displayName(string $code, string $displayLocale): ?string
  {
    if (! class_exists(\Locale::class)) {
      return self::FALLBACK_NAMES[$code] ?? null;
    }

    $name = trim((string) \Locale::getDisplayName($this->icuCode($code), $displayLocale));

    return $name !== '' ? $name : (self::FALLBACK_NAMES[$code] ?? null);
  }

  private function icuCode(string $code): string
  {
    return str_replace('-', '_', $code);
  }
}
