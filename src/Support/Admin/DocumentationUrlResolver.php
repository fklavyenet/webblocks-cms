<?php

namespace WebBlocks\Cms\Support\Admin;

use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;

class DocumentationUrlResolver
{
  private const DEFAULT_URL = 'https://cms.webblocksui.com';

  public function url(string $locale): string
  {
    $configuredUrl = rtrim((string) config('webblocks-cms.admin.documentation_url', self::DEFAULT_URL), '/');
    $scheme = parse_url($configuredUrl, PHP_URL_SCHEME);
    $baseUrl = filter_var($configuredUrl, FILTER_VALIDATE_URL) !== false && in_array($scheme, ['http', 'https'], true)
      ? $configuredUrl
      : self::DEFAULT_URL;
    $resolvedLocale = in_array($locale, AdminLocaleResolver::SUPPORTED_LOCALES, true) ? $locale : 'en';

    return $resolvedLocale === 'en' ? $baseUrl : $baseUrl.'/'.$resolvedLocale;
  }
}
