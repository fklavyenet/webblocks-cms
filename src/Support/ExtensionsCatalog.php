<?php

namespace WebBlocks\Cms\Support;

final class ExtensionsCatalog
{
  private const DEFAULT_BASE_URL = 'https://extensions.webblocksui.com';

  public static function pluginBaseUrl(): string
  {
    return self::configuredUrl(config('webblocks-plugins.catalog.base_url'))
      ?? self::configuredUrl(config('webblocks-plugins.catalog.extensions_base_url'))
      ?? self::DEFAULT_BASE_URL;
  }

  public static function themeBaseUrl(): ?string
  {
    return self::secureBaseUrl();
  }

  public static function secureBaseUrl(): ?string
  {
    $override = config('webblocks-plugins.catalog.extensions_base_url');
    $url = $override === null ? self::pluginBaseUrl() : self::configuredUrl($override);
    if ($url === null) {
      return null;
    }

    $parts = parse_url($url);

    return is_array($parts) && ($parts['scheme'] ?? null) === 'https' && isset($parts['host'])
      && array_diff(array_keys($parts), ['scheme', 'host', 'port']) === []
      && filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
  }

  private static function configuredUrl(mixed $value): ?string
  {
    $url = is_string($value) ? rtrim(trim($value), '/') : '';

    return $url !== '' ? $url : null;
  }
}
