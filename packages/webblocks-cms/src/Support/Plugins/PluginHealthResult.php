<?php

namespace WebBlocks\Cms\Support\Plugins;

class PluginHealthResult
{
  public const HEALTHY = 'healthy';

  public const WARNING = 'warning';

  public const UNKNOWN = 'unknown';

  public const UNAVAILABLE = 'unavailable';

  public function __construct(
    public readonly string $status,
    public readonly string $message = '',
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

  /**
   * @return array{status: string, message: string}
   */
  public function toArray(): array
  {
    return [
      'status' => $this->status,
      'message' => $this->message,
    ];
  }
}
