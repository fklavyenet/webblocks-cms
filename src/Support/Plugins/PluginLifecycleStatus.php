<?php

namespace WebBlocks\Cms\Support\Plugins;

class PluginLifecycleStatus
{
  public const ENABLED = 'enabled';

  public const DISABLED = 'disabled';

  public function __construct(
    public readonly string $value,
    public readonly string $label,
  ) {}

  public static function enabled(): self
  {
    return new self(self::ENABLED, 'Enabled');
  }

  public static function disabled(): self
  {
    return new self(self::DISABLED, 'Disabled');
  }

  public static function fromEnabled(bool $enabled): self
  {
    return $enabled ? self::enabled() : self::disabled();
  }

  /**
   * @return array{value: string, label: string}
   */
  public function toArray(): array
  {
    return [
      'value' => $this->value,
      'label' => $this->label,
    ];
  }
}
