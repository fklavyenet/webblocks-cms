<?php

namespace WebBlocks\Cms\Support\System;

class SystemHealthStatus
{
  public const ORDER = ['critical' => 0, 'warning' => 1, 'unknown' => 2, 'healthy' => 3, 'not_applicable' => 4];

  public static function aggregate(array $checks): string
  {
    $statuses = array_column($checks, 'status');
    foreach (array_keys(self::ORDER) as $status) {
      if (in_array($status, $statuses, true)) {
        return $status;
      }
    }

    return 'not_applicable';
  }

  public static function lead(array $checks): array
  {
    usort($checks, fn (array $a, array $b) => self::ORDER[$a['status']] <=> self::ORDER[$b['status']]);

    return $checks[0];
  }

  public static function check(string $code, string $status, string $message, string $route, array $parameters = [], array $route_parameters = []): array
  {
    return compact('code', 'status', 'message', 'route', 'parameters', 'route_parameters');
  }
}
