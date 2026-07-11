<?php

namespace WebBlocks\Cms\Support\Plugins\Contracts;

interface PluginAdminExtension
{
  public function pluginHandle(): string;

  public function key(): string;

  public function permissionName(): ?string;
}
