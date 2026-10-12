<?php

namespace WebBlocks\Cms\Support\System;

use Throwable;
use WebBlocks\Cms\Models\SystemSetting;

class RequestSecuritySettings
{
  public const DEFINITIONS = [
    'pages_per_minute' => ['config' => 'cms.public_rate_limits.pages_per_minute', 'default' => 120, 'max' => 1000000],
    'pages_per_hour' => ['config' => 'cms.public_rate_limits.pages_per_hour', 'default' => 1800, 'max' => 1000000],
    'search_per_minute' => ['config' => 'cms.public_rate_limits.search_per_minute', 'default' => 30, 'max' => 1000000],
    'search_per_hour' => ['config' => 'cms.public_rate_limits.search_per_hour', 'default' => 300, 'max' => 1000000],
    'api_per_minute' => ['config' => 'cms.internal_api.rate_limit_per_minute', 'default' => 120, 'max' => 1000000],
    'api_ip_per_minute' => ['config' => 'cms.internal_api.ip_rate_limit_per_minute', 'default' => 300, 'max' => 1000000],
    'search_max_characters' => ['config' => null, 'default' => 200, 'max' => 2000],
    'search_max_words' => ['config' => null, 'default' => 20, 'max' => 100],
  ];

  public static function key(string $field): string
  {
    return 'system.request_security.'.$field;
  }

  public static function keys(): array
  {
    return array_map(self::key(...), array_keys(self::DEFINITIONS));
  }

  public function values(): array
  {
    $stored = $this->stored();
    $values = [];

    foreach (self::DEFINITIONS as $field => $definition) {
      $configured = $definition['config'] !== null ? (int) config($definition['config'], $definition['default']) : $definition['default'];
      $fallback = $configured > 0 ? $configured : $definition['default'];
      $override = filter_var($stored[self::key($field)] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => $definition['max']],
      ]);
      $values[$field] = is_int($override) ? $override : $fallback;
    }

    return $values;
  }

  public function hasOverrides(): bool
  {
    return $this->stored() !== [];
  }

  private function stored(): array
  {
    try {
      return SystemSetting::query()->whereIn('key', self::keys())->pluck('value', 'key')->all();
    } catch (Throwable) {
      // Preserve installation defaults while the settings schema is unavailable.
      return [];
    }
  }
}
