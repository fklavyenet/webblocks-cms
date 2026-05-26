<?php

namespace WebBlocks\Cms\Support\Plugins;

class PluginHealthResult
{
  public function __construct(
    public readonly string $status,
    public readonly string $message = '',
  ) {}

  public static function unknown(string $message = 'Health checks are not implemented for this plugin.'): self
  {
    return new self('unknown', $message);
  }
}
