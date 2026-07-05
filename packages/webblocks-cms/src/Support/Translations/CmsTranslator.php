<?php

namespace WebBlocks\Cms\Support\Translations;

use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class CmsTranslator
{
  public function get(string $key, ?string $locale = null, array $replace = []): string
  {
    foreach ($this->candidateLocales($locale) as $candidateLocale) {
      $value = trans(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::'.$key, $replace, $candidateLocale);

      if (is_string($value) && $value !== WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::'.$key) {
        return $value;
      }
    }

    return $this->replace($key, $replace);
  }

  public function public(string $key, ?string $locale = null, array $replace = []): string
  {
    return $this->get('public.'.$key, $locale, $replace);
  }

  public function admin(string $key, ?string $locale = null, array $replace = []): string
  {
    return $this->get('admin.'.$key, $locale, $replace);
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
