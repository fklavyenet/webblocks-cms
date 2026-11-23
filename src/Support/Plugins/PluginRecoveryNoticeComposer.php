<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\View\View;
use WebBlocks\Cms\Policies\Plugins\PluginRecoveryPolicy;
use WebBlocks\Cms\Support\Extensions\ExtensionOperation;

class PluginRecoveryNoticeComposer
{
  public function compose(View $view): void
  {
    $repository = app(InstalledPluginRepository::class);
    $failures = app(PluginRecoveryPolicy::class)->manage(request()->user())
      ? array_filter($repository->installed(), fn (array $plugin): bool => $repository->runtimeFailed($plugin['manifest']['handle'], $plugin['manifest']['version']))
      : [];
    $view->with('pluginRuntimeFailures', array_map(static function (array $plugin) use ($repository): string {
      $manifest = $plugin['manifest'];

      return ExtensionOperation::recoveryMessage(
        $manifest['handle'],
        $manifest['version'],
        $manifest['label'] ?? null,
        $repository->runtimeFailureReference($manifest['handle'], $manifest['version']),
      );
    }, array_values($failures)));
  }
}
