<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\ServiceProvider;
use Throwable;

class PluginProviderRegistrar
{
  public function register(): void
  {
    // Register after the host has booted so Laravel boots each provider inside
    // the guarded call. Route caching must not skip bindings or view composers.
    app()->booted(function (): void {
      $registry = app(PluginRegistry::class);
      foreach ($registry->enabled() as $plugin) {
        if (! $registry->isActive($plugin->handle())) {
          continue;
        }
        $provider = $plugin->providerClass();
        if (! $provider || ! is_subclass_of($provider, ServiceProvider::class)) {
          continue;
        }
        try {
          app()->register($provider);
        } catch (Throwable) {
          app(PluginRuntimeGuard::class)->quarantine($plugin);
        }
      }
    });
  }
}
