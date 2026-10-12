<?php

namespace WebBlocks\Cms\Support\InternalContentApi;

use WebBlocks\Cms\Support\System\RequestSecuritySettings;

/**
 * One source for the Internal Content API request budgets, so the throttle that
 * enforces it and the discovery documents that advertise it cannot drift apart.
 * A bulk client such as a full-site translation pass can only pace itself if the
 * number it reads from the API is the number the API actually applies.
 */
class InternalApiRateLimit
{
  public const DEFAULT_PER_MINUTE = 120;

  public const DEFAULT_IP_PER_MINUTE = 300;

  public static function perMinute(): int
  {
    return self::budgets()['per_minute'];
  }

  public static function perIpPerMinute(): int
  {
    return self::budgets()['ip_per_minute'];
  }

  public static function budgets(): array
  {
    $limits = app(RequestSecuritySettings::class)->values();

    return ['per_minute' => $limits['api_per_minute'], 'ip_per_minute' => $limits['api_ip_per_minute']];
  }
}
