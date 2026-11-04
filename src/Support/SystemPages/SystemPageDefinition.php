<?php

namespace WebBlocks\Cms\Support\SystemPages;

use InvalidArgumentException;

final readonly class SystemPageDefinition
{
  public function __construct(public string $key, public string $labelKey, public array $routes = [])
  {
    if (strlen($key) > 150 || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*:[a-z0-9]+(?:-[a-z0-9]+)*$/D', $key)) {
      throw new InvalidArgumentException('Invalid system page key.');
    }
  }

  public function label(): string
  {
    return __($this->labelKey);
  }
}
