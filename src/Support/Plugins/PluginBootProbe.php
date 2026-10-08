<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateCommandRunner;

class PluginBootProbe
{
  public const SUCCESS = 'WEBBLOCKS_PLUGIN_PROBE_OK';

  public function check(PluginDefinition $plugin): void
  {
    try {
      $result = Process::path(base_path())
        ->timeout(max(1, (int) config('webblocks-plugins.install.boot_timeout_seconds', 30)))
        ->env(['WEBBLOCKS_PLUGIN_INSTALL_ROOT' => app(InstalledPluginRepository::class)->rootPath(), 'WEBBLOCKS_PLUGIN_SAFE_MODE' => '1'])
        ->run((new UpdateCommandRunner)->artisanCommand(['cms:plugin-probe', $plugin->handle(), $plugin->versionText(), '--no-interaction']));
      if (! $result->successful() || trim($result->output()) !== self::SUCCESS) {
        throw new RuntimeException('Plugin probe did not complete.');
      }
    } catch (Throwable) {
      // Plugin output and exception text can contain credentials. Do not expose it.
      throw new RuntimeException(__('webblocks-cms::admin.plugin_recovery.probe_failed'));
    }
  }
}
