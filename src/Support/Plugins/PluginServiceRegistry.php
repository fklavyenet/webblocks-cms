<?php

namespace WebBlocks\Cms\Support\Plugins;

/** Resolve at call time so disabling or quarantining a provider removes access. */
class PluginServiceRegistry
{
  public function resolve(string $handle, string $contract, string $constraint): ?object
  {
    $registry = app(PluginRegistry::class);
    $provider = $registry->get($handle);
    if (! $provider || ! $registry->isActive($handle) || ! PluginDependencies::satisfies((string) $provider->versionText(), $constraint)) {
      return null;
    }
    $class = $provider->serviceDefinitions()[$contract] ?? null;
    if (! is_string($class) || ! is_a($class, $contract, true)) {
      return null;
    }

    return app($class);
  }
}
