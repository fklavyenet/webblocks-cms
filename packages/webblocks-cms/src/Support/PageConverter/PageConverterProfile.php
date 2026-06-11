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
    return [
      self::CONSERVATIVE => 'Conservative',
      self::GENERIC_DOCS => 'Generic Docs Page',
      self::GENERIC_MARKETING => 'Generic Marketing Page',
      self::WEBBLOCKS_UI => 'WebBlocks UI-flavored HTML',
    ];
  }

  public static function values(): array
  {
    return array_keys(self::options());
  }

  public static function label(string $profile): string
  {
    return self::options()[$profile] ?? self::options()[self::CONSERVATIVE];
  }
}
