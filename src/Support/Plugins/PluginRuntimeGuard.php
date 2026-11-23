<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\Route;
use Throwable;
use WebBlocks\Cms\Support\Extensions\ExtensionOperation;

class PluginRuntimeGuard
{
  public function quarantine(PluginDefinition $plugin, string $stage = 'startup'): void
  {
    $this->quarantineIn(app(PluginRegistry::class), $plugin, $stage);
  }

  public function quarantineIn(PluginRegistry $registry, PluginDefinition $plugin, string $stage): void
  {
    $registry->suppress($plugin->handle());
    $operation = ExtensionOperation::plugin('startup', $plugin, $stage);
    $operation->record();
    try {
      if ($plugin->installPathValue() !== null && $plugin->versionText() !== null) {
        app(InstalledPluginRepository::class)->quarantine($plugin->handle(), $plugin->versionText(), $operation->reference);
      }
    } catch (Throwable) {
      // Suppression still protects this request when state storage is read-only.
    }
  }

  public function routes(PluginDefinition $plugin, callable $register): void
  {
    $router = app('router');
    $groups = $router->getGroupStack();
    $routes = clone Route::getRoutes();
    try {
      $register();
    } catch (Throwable) {
      Route::setRoutes($routes);
      // Router::group does not unwind its protected stack when plugin code throws.
      // Restore the snapshot so the next plugin cannot inherit its prefix or guards.
      (function (array $stack): void {
      $this->groupStack = $stack;
      })->call($router, $groups);
      $this->quarantine($plugin, 'routes');
    }
  }
}
