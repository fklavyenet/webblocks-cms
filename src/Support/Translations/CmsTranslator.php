<?php

namespace WebBlocks\Cms\Support\Translations;

use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class CmsTranslator
{
  public function get(string $key, ?string $locale = null, array $replace = []): string
  {
    $translationKey = WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::'.$key;

    foreach ($this->candidateLocales($locale) as $candidateLocale) {
      if (! app('translator')->hasForLocale($translationKey, $candidateLocale)) {
        continue;
      }

      $value = trans($translationKey, $replace, $candidateLocale);

      if (is_string($value)) {
        return $value;
      }
    }

    return $this->replace($key, $replace);
  }

  /**
   * Return the translation for a key, or the given default when no translation
   * exists. Used for plugin-contributed strings (e.g. token capability groups)
   * that have no CMS lang entry but ship their own labels.
   */
  public function getOrDefault(string $key, string $default, ?string $locale = null, array $replace = []): string
  {
    $translationKey = WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::'.$key;

    foreach ($this->candidateLocales($locale) as $candidateLocale) {
      if (app('translator')->hasForLocale($translationKey, $candidateLocale)) {
        $value = trans($translationKey, $replace, $candidateLocale);

        if (is_string($value)) {
          return $value;
        }
      }
    }

    return $this->replace($default, $replace);
  }

  public function public(string $key, ?string $locale = null, array $replace = []): string
  {
    return $this->get('public.'.$key, $locale, $replace);
  }

  public function admin(string $key, ?string $locale = null, array $replace = []): string
  {
    return $this->get('admin.'.$key, $locale, $replace);
  }

  public function plugin(string $handle, string $key, ?string $locale = null, array $replace = [], ?string $fallback = null): string
  {
    $translationKey = $handle.'::'.$key;

    foreach ($this->candidateLocales($locale) as $candidateLocale) {
      if (! app('translator')->hasForLocale($translationKey, $candidateLocale)) {
        continue;
      }

      $value = trans($translationKey, $replace, $candidateLocale);

      if (is_string($value)) {
        return $value;
      }
    }

    return $this->replace($fallback ?? $key, $replace);
  }

  private function candidateLocales(?string $locale): array
  {
    $locale = Locale::normalizeCode($locale);
    $fallback = Locale::normalizeCode(config('app.fallback_locale', 'en')) ?: 'en';

    return collect([
      $locale,
      $this->baseLocale($locale),
      $fallback,
      $this->baseLocale($fallback),
      'en',
    ])
      ->filter()
      ->unique()
      ->values()
      ->all();
  }

  private function baseLocale(?string $locale): ?string
  {
    if (! $locale || ! str_contains($locale, '-')) {
      return null;
    }

    return str($locale)->before('-')->toString();
  }

  private function replace(string $value, array $replace): string
  {
    foreach ($replace as $key => $replacement) {
      $value = str_replace(':'.$key, (string) $replacement, $value);
    }

    return $value;
  }
}
