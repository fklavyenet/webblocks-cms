<?php

namespace WebBlocks\Cms\Support\Sites;

use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteVariable;

class SiteTokenRenderer
{
  private const TOKEN_PATTERN = '/\{\{\s*site\.([a-z][a-z0-9_]*)\s*\}\}/';

  public function render(?string $value, ?Site $site, bool $escapeReplacement = false): ?string
  {
    if ($value === null || $value === '' || ! $site) {
      return $value;
    }

    $variables = $this->variablesFor($site);

    if ($variables === []) {
      return $value;
    }

    return preg_replace_callback(self::TOKEN_PATTERN, function (array $matches) use ($variables, $escapeReplacement) {
      $key = $matches[1] ?? null;

      if (! is_string($key) || ! array_key_exists($key, $variables)) {
        return $matches[0];
      }

      $replacement = $variables[$key];

      return $escapeReplacement
        ? e($replacement)
        : $replacement;
    }, $value) ?? $value;
  }

  private function variablesFor(Site $site): array
  {
    $variables = $site->relationLoaded('siteVariables')
      ? $site->siteVariables
      : $site->siteVariables()->where('is_enabled', true)->orderBy('sort_order')->orderBy('id')->get();

    return $variables
      ->filter(fn (SiteVariable $siteVariable) => $siteVariable->is_enabled)
      ->mapWithKeys(fn (SiteVariable $siteVariable) => [$siteVariable->key => (string) ($siteVariable->value ?? '')])
      ->all();
  }
}
