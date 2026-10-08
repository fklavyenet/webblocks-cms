<?php

namespace WebBlocks\Cms\Support\Plugins;

class PluginRecoveryMode
{
  public function active(): bool
  {
    if (app()->runningInConsole() && getenv('WEBBLOCKS_PLUGIN_SAFE_MODE') === '1') {
      return true;
    }

    return app()->bound('request') && request()->is('webadmin/plugin-recovery', 'webadmin/plugin-recovery/*');
  }
}
