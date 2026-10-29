<?php

namespace WebBlocks\Cms\Support\Consent;

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Pages\PublicMount;

final class ConsentPreferencesLink
{
  public function url(Site $site, ?string $locale = null): string
  {
    $locales = app(LocaleResolver::class);
    $language = $locale ? $locales->enabled($locale, $site) : null;
    $language ??= $locales->default();

    if (! Route::has('public.privacy-consent.preferences')) {
      return app(PublicMount::class)->path(($language->is_default ? '' : '/'.$language->code).'/privacy-consent/preferences');
    }

    return $language->is_default
      ? route('public.privacy-consent.preferences', [], false)
      : route('public.privacy-consent.localized-preferences', ['locale' => $language->code], false);
  }

  public function label(Site $site, ?string $locale = null): string
  {
    return app(ConsentConfiguration::class)->text($site, 'settings', $locale);
  }
}
