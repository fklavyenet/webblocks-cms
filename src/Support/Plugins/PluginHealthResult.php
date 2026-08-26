<?php

namespace WebBlocks\Cms\Support\Plugins;

class PluginHealthResult
{
  public const HEALTHY = 'healthy';

  public const WARNING = 'warning';

  public const UNKNOWN = 'unknown';

  public const UNAVAILABLE = 'unavailable';

  public const INCOMPATIBLE = 'incompatible';

  public function __construct(
    public readonly string $status,
    public readonly string $message = '',
    /** @var array<int, array{name: string, status: string, message: string}> */
    public readonly array $checks = [],
  ) {}

  public static function healthy(string $message = 'Plugin is enabled.'): self
  {
    return new self(self::HEALTHY, $message);
  }

  public static function warning(string $message): self
  {
    return new self(self::WARNING, $message);
  }

  public static function unknown(string $message = 'Health checks are not implemented for this plugin.'): self
  {
    return new self(self::UNKNOWN, $message);
  }

  public static function unavailable(string $message = 'Plugin is disabled.'): self
  {
    return new self(self::UNAVAILABLE, $message);
  }

  public static function incompatible(string $message): self
  {
    return new self(self::INCOMPATIBLE, $message);
  }

  /**
   * @param  array<int, array{name: string, status: string, message?: string}>  $checks
   */
  public static function withChecks(string $status, string $message, array $checks): self
  {
    return new self($status, $message, array_map(
      static fn (array $check): array => [
        'name' => trim((string) ($check['name'] ?? '')),
        'status' => trim((string) ($check['status'] ?? self::UNKNOWN)),
        'message' => trim((string) ($check['message'] ?? '')),
      ],
      $checks,
    ));
  }

  /**
   * @return array{status: string, message: string, checks: array<int, array{name: string, status: string, message: string}>}
   */
  public function toArray(): array
  {
    return [
      'status' => $this->status,
      'message' => $this->message,
      'checks' => $this->checks,
    ];
  }
}
