<?php

namespace WebBlocks\Cms\Support\PageConverter;

class PageConverterProfile
{
  public const CONSERVATIVE = 'conservative';

  public const GENERIC_DOCS = 'generic_docs';

  public const GENERIC_MARKETING = 'generic_marketing';

  public const WEBBLOCKS_UI = 'webblocks_ui';

  public static function options(): array
  {
    $profiles = self::values();

    return array_combine($profiles, array_map(self::label(...), $profiles));
  }

  public static function values(): array
  {
    return [self::CONSERVATIVE, self::GENERIC_DOCS, self::GENERIC_MARKETING, self::WEBBLOCKS_UI];
  }

  public static function label(string $profile): string
  {
    $profile = in_array($profile, self::values(), true) ? $profile : self::CONSERVATIVE;

    return __('webblocks-cms::admin.converter_profiles.'.$profile);
  }
}
