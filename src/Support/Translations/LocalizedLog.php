<?php

namespace WebBlocks\Cms\Support\Translations;

use Closure;

final class LocalizedLog
{
  public static function templates(string $group, ?array $keys = null): array
  {
    $entries = __('webblocks-cms::'.$group, [], 'en');
    if ($keys !== null) {
      $entries = array_intersect_key($entries, array_flip($keys));
    }

    $templates = [];
    foreach ($entries as $key => $entry) {
      $templates[$group.'.'.$key] = $entry;
    }

    return $templates;
  }

  public static function display(?string $log, string $locale, array $templates, ?Closure $phaseLabel = null): ?string
  {
    if ($locale === 'en' || $log === null || $log === '') {
      return $log;
    }

    $patterns = [];
    foreach ($templates as $key => $template) {
      $parts = preg_split('/(:[a-z_]+)/', $template, -1, PREG_SPLIT_DELIM_CAPTURE);
      $pattern = implode('', array_map(static function (string $part): string {
        return preg_match('/\A:([a-z_]+)\z/', $part, $placeholder) === 1
          ? '(?P<'.$placeholder[1].'>.*?)'
          : preg_quote($part, '~');
      }, $parts));
      $patterns[$key] = '~\A'.$pattern.'\z~';
    }

    $translator = app(CmsTranslator::class);

    return implode('', array_map(
      static fn (string $line): string => self::line($line, $locale, $patterns, $translator, $phaseLabel),
      preg_split('/(\R)/', $log, -1, PREG_SPLIT_DELIM_CAPTURE),
    ));
  }

  private static function line(string $line, string $locale, array $patterns, CmsTranslator $translator, ?Closure $phaseLabel, bool $translateDetail = true): string
  {
    foreach ($patterns as $key => $pattern) {
      if (preg_match($pattern, $line, $matches) !== 1) {
        continue;
      }

      $replace = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
      if (isset($replace['phase']) && $phaseLabel !== null) {
        $replace['phase'] = $phaseLabel($replace['phase']);
      }
      if ($translateDetail && isset($replace['detail'])) {
        $replace['detail'] = self::line($replace['detail'], $locale, $patterns, $translator, $phaseLabel, false);
      }

      return $translator->get($key, $locale, $replace);
    }

    return $line;
  }
}
