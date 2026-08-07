<?php

namespace WebBlocks\Cms\Support\InternalContentApi;

/**
 * One source for the Internal Content API request budget, so the throttle that
 * enforces it and the discovery documents that advertise it cannot drift apart.
 * A bulk client such as a full-site translation pass can only pace itself if the
 * number it reads from the API is the number the API actually applies.
 */
class InternalApiRateLimit
{
  public const DEFAULT_PER_MINUTE = 120;

  public static function perMinute(): int
  {
    $configured = (int) config('cms.internal_api.rate_limit_per_minute', self::DEFAULT_PER_MINUTE);

    return $configured > 0 ? $configured : self::DEFAULT_PER_MINUTE;
  }
}
