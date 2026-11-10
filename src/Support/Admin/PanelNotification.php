<?php

namespace WebBlocks\Cms\Support\Admin;

final readonly class PanelNotification
{
  /** The label key is pluralized with the count in the active panel locale. */
  public function __construct(
    public int $count,
    public string $labelKey,
    public string $route,
    public array $routeParameters = [],
  ) {}
}
