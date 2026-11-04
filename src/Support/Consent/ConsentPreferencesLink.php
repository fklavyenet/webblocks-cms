<?php

namespace WebBlocks\Cms\Support\Consent;

use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Locales\LocaleResolver;

final class ConsentPreferencesLink
{
  public function url(Site $site, ?string $locale = null): string
  {
    return '#cookie-settings';
  }

  public function saveUrl(Site $site, ?string $locale = null): string
  {
    $locales = app(LocaleResolver::class);
    $language = $locale ? $locales->enabled($locale, $site) : null;
    $language ??= $locales->default();

    return $language->is_default
      ? route('public.privacy-consent.preferences-save', [], false)
      : route('public.privacy-consent.localized-preferences-save', ['locale' => $language->code], false);
  }

  public function label(Site $site, ?string $locale = null): string
  {
    return app(ConsentConfiguration::class)->text($site, 'settings', $locale);
  }
}
