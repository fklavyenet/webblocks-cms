<?php

namespace WebBlocks\Cms\Support\Locales;

use WebBlocks\Cms\Models\Locale;

class LocaleOptionCatalog
{
  private const STANDARD_CODES = [
    'en',
    'de',
    'tr',
    'fr',
    'es',
    'it',
    'nl',
    'pt',
    'pl',
    'sv',
    'da',
    'no',
    'fi',
    'cs',
    'sk',
    'sl',
    'hu',
    'ro',
    'bg',
    'el',
    'hr',
    'sr',
    'bs',
    'sq',
    'et',
    'lv',
    'lt',
    'uk',
    'ar',
    'he',
    'fa',
    'ur',
    'hi',
    'bn',
    'ta',
    'te',
    'mr',
    'ru',
    'zh',
    'ja',
    'ko',
    'id',
    'ms',
    'th',
    'vi',
    'sw',
    'af',
    'ca',
    'eu',
    'gl',
    'is',
    'ga',
    'mt',
    'mk',
    'kk',
    'az',
    'hy',
    'ka',
  ];

  private const FALLBACK_NAMES = [
    'af' => 'Afrikaans',
    'ar' => 'Arabic',
    'az' => 'Azerbaijani',
    'bg' => 'Bulgarian',
    'bn' => 'Bengali',
    'bs' => 'Bosnian',
    'ca' => 'Catalan',
    'cs' => 'Czech',
    'da' => 'Danish',
    'de' => 'German',
    'el' => 'Greek',
    'en' => 'English',
    'es' => 'Spanish',
    'et' => 'Estonian',
    'eu' => 'Basque',
    'fa' => 'Persian',
    'fi' => 'Finnish',
    'fr' => 'French',
    'ga' => 'Irish',
    'gl' => 'Galician',
    'he' => 'Hebrew',
    'hi' => 'Hindi',
    'hr' => 'Croatian',
    'hu' => 'Hungarian',
    'hy' => 'Armenian',
    'id' => 'Indonesian',
    'is' => 'Icelandic',
    'it' => 'Italian',
    'ja' => 'Japanese',
    'ka' => 'Georgian',
    'kk' => 'Kazakh',
    'ko' => 'Korean',
    'lt' => 'Lithuanian',
    'lv' => 'Latvian',
    'mk' => 'Macedonian',
    'mr' => 'Marathi',
    'ms' => 'Malay',
    'mt' => 'Maltese',
    'nl' => 'Dutch',
    'no' => 'Norwegian',
    'pl' => 'Polish',
    'pt' => 'Portuguese',
    'ro' => 'Romanian',
    'ru' => 'Russian',
    'sk' => 'Slovak',
    'sl' => 'Slovenian',
    'sq' => 'Albanian',
    'sr' => 'Serbian',
    'sv' => 'Swedish',
    'sw' => 'Swahili',
    'ta' => 'Tamil',
    'te' => 'Telugu',
    'th' => 'Thai',
    'tr' => 'Turkish',
    'uk' => 'Ukrainian',
    'ur' => 'Urdu',
    'vi' => 'Vietnamese',
    'zh' => 'Chinese',
  ];

  public function groupedOptions(array $installedCodes = []): array
  {
    $options = $this->options($installedCodes);

    return [
      'standard' => $options,
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
    return collect(self::STANDARD_CODES)
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
      'is_standard' => in_array($code, self::STANDARD_CODES, true),
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
