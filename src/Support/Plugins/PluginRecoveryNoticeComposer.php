<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\View\View;
use WebBlocks\Cms\Policies\Plugins\PluginRecoveryPolicy;

class PluginRecoveryNoticeComposer
{
  public function compose(View $view): void
  {
    $repository = app(InstalledPluginRepository::class);
    $failures = app(PluginRecoveryPolicy::class)->manage(request()->user())
      ? array_filter($repository->installed(), fn (array $plugin): bool => $repository->runtimeFailed($plugin['manifest']['handle'], $plugin['manifest']['version']))
      : [];
    $view->with('pluginRuntimeFailures', array_values($failures));
  }
}
