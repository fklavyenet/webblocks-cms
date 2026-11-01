<?php

namespace WebBlocks\Cms\Support\Updates;

final class CmsUpdateMaintenance
{
  public const VIEW = 'webblocks-cms::errors.503';

  public const RETRY_AFTER = 60;

  public static function arguments(): array
  {
    return ['down', '--render='.self::VIEW, '--status=503', '--retry='.self::RETRY_AFTER];
  }
}
