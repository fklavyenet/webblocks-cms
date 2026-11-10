<?php

namespace WebBlocks\Cms\Support\Sites;

final class SiteSettingsFields
{
  public const SEO_LENGTHS = ['seo_title' => 255, 'seo_description' => 1000, 'seo_keywords' => 500];

  public const CONTACT_RECIPIENT_RULES = ['nullable', 'email:rfc', 'max:255'];

  public const CUSTOM_HEAD_RULES = ['nullable', 'string', 'max:65000'];

  public static function seoRules(): array
  {
    return array_map(fn (int $max): array => ['nullable', 'string', 'max:'.$max], self::SEO_LENGTHS);
  }

  public static function blankToNull(?string $value): ?string
  {
    return trim((string) $value) === '' ? null : $value;
  }
}
