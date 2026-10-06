<?php

namespace WebBlocks\Cms\Support\Blocks;

class MobileBlockMedia
{
  public const ROLE = 'mobile_image';

  public const MEDIA_QUERY = '(max-width: 768px)';

  public const BLOCK_TYPES = ['slide', 'image', 'hero', 'section', 'card', 'cta', 'content_header', 'link-list-item'];

  public static function supports(?string $type): bool
  {
    return in_array($type, self::BLOCK_TYPES, true);
  }
}
