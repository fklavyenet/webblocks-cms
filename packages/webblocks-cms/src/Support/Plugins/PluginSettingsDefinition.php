<?php

namespace WebBlocks\Cms\Support\Plugins;

class PluginSettingsDefinition
{
  public function __construct(
    public readonly ?string $routeName = null,
    public readonly ?string $schemaClass = null,
  ) {}
}
