<?php

namespace WebBlocks\Cms\Support\Plugins;

class PluginRecoveryMode
{
  public function isolatesRuntime(): bool
  {
    return $this->active() || (app()->bound('request') && request()->is('webadmin/two-factor', 'webadmin/two-factor/*'));
  }

  public function active(): bool
  {
    if (app()->runningInConsole() && getenv('WEBBLOCKS_PLUGIN_SAFE_MODE') === '1') {
      return true;
    }

    return app()->bound('request') && request()->is('webadmin/plugin-recovery', 'webadmin/plugin-recovery/*');
  }
}
