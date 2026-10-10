<?php

namespace WebBlocks\Cms\Support\Admin;

class DocumentationUrlResolver
{
  private const DEFAULT_URL = 'https://cms.webblocksui.com/docs';

  public function url(string $locale): string
  {
    $configuredUrl = rtrim((string) config('webblocks-cms.admin.documentation_url', self::DEFAULT_URL), '/');
    $scheme = parse_url($configuredUrl, PHP_URL_SCHEME);
    $baseUrl = filter_var($configuredUrl, FILTER_VALIDATE_URL) !== false && in_array($scheme, ['http', 'https'], true)
      ? $configuredUrl
      : self::DEFAULT_URL;

    // Older installations explicitly configured the official site root.
    if (parse_url($baseUrl, PHP_URL_HOST) === 'cms.webblocksui.com' && in_array(parse_url($baseUrl, PHP_URL_PATH), [null, '', '/'], true)) {
      return self::DEFAULT_URL;
    }

    return $baseUrl;
  }
}
