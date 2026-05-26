<?php

namespace WebBlocks\Cms\Support\Plugins\Contracts;

interface PluginBlockExtension
{
  public function pluginHandle(): string;

  public function handle(): string;
}
