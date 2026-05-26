<?php

namespace WebBlocks\Cms\Support\Plugins\Contracts;

interface PluginPublicAssetExtension
{
  public function pluginHandle(): string;

  public function handle(): string;
}
