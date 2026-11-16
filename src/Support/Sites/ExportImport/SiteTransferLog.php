<?php

namespace WebBlocks\Cms\Support\Sites\ExportImport;

use WebBlocks\Cms\Support\Translations\CmsTranslator;

final class SiteTransferLog
{
  private const GROUP = 'admin-feedback.transfer_log';

  public static function entry(string $key, array $replace = []): string
  {
    // Keep the stored text stable for existing histories and CLI consumers.
    return __(self::translationKey($key), $replace, 'en');
  }

  public static function display(?string $log, string $locale): string
  {
    if ($locale === 'en' || $log === null || $log === '') {
      return $log ?? '';
    }

    $patterns = [];
    foreach (__(self::translationKey(), [], 'en') as $key => $template) {
      $parts = preg_split('/(:[a-z_]+)/', $template, -1, PREG_SPLIT_DELIM_CAPTURE);
      $pattern = implode('', array_map(static function (string $part): string {
        if (str_starts_with($part, ':') && ctype_alpha(substr($part, 1))) {
          return '(?P<'.substr($part, 1).'>.+?)';
        }

        return preg_quote($part, '~');
      }, $parts));
      $patterns[$key] = '~\A'.$pattern.'\z~';
    }

    $translator = app(CmsTranslator::class);

    return implode('', array_map(
      static function (string $line) use ($locale, $patterns, $translator): string {
        foreach ($patterns as $key => $pattern) {
          if (preg_match($pattern, $line, $matches) !== 1) {
            continue;
          }

          $replace = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
          if (isset($replace['phase'])) {
            $replace['phase'] = $translator->getOrDefault('admin.site_transfers.import_phases.'.$replace['phase'], $replace['phase'], $locale);
          }

          return $translator->get(self::GROUP.'.'.$key, $locale, $replace);
        }

        return $line;
      },
      preg_split('/(\R)/', $log, -1, PREG_SPLIT_DELIM_CAPTURE),
    ));
  }

  private static function translationKey(?string $key = null): string
  {
    return 'webblocks-cms::'.self::GROUP.($key === null ? '' : '.'.$key);
  }
}
