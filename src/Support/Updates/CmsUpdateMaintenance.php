<?php

namespace WebBlocks\Cms\Support\Updates;

final class CmsUpdateMaintenance
{
  public const VIEW = 'webblocks-cms::errors.503';

  public const RETRY_AFTER = 60;

  public static function active(): bool
  {
    // Cache clearing must not erase proof of the supervisor's rendered maintenance gate.
    return is_file(storage_path('framework/maintenance.php')) || app()->isDownForMaintenance();
  }

  public static function arguments(): array
  {
    return ['down', '--render='.self::VIEW, '--status=503', '--retry='.self::RETRY_AFTER];
  }
}
