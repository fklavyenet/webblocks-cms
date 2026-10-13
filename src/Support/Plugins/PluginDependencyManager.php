<?php

namespace WebBlocks\Cms\Support\Plugins;

use RuntimeException;

/** Lifecycle checks use manifest metadata without executing candidate code. */
class PluginDependencyManager
{
  public function __construct(private readonly InstalledPluginRepository $repository) {}

  private function snapshot(): array
  {
    $plugins = [];
    foreach ($this->repository->installed() as $installed) {
      $definition = $this->definition($installed['manifest']);
      $plugins[$definition->handle()] = $definition;
    }

    return $plugins;
  }

  private function definition(array $manifest): PluginDefinition
  {
    return PluginDefinition::make($manifest['handle'])->label($manifest['label'])
      ->version($manifest['version'])->requiresCms($manifest['required_cms_version'] ?? null)
      ->dependencies($manifest['dependencies'] ?? []);
  }

  private function configured(string $handle): bool
  {
    return ! $this->repository->isDisabled($handle)
      && ($this->repository->enabledVersion($handle) !== null || (bool) config("webblocks-plugins.enabled.{$handle}", false));
  }

  private function enabled(string $handle): bool
  {
    if (! $this->configured($handle)) {
    return false;
    }
    $entry = collect($this->repository->installed())->first(fn ($item) => ($item['manifest']['handle'] ?? null) === $handle);
    if ($entry !== null && in_array($this->repository->setupResult($handle, $entry['manifest']['version'])['status'] ?? null, ['running', 'failed'], true)) {
    return false;
    }

    return $this->repository->enabledVersion($handle) !== null || (bool) config("webblocks-plugins.enabled.{$handle}", false);
  }

  public function assertEnable(array $manifest): void
  {
    $plugins = $this->snapshot();
    $candidate = $this->definition($manifest);
    $plugins[$candidate->handle()] = $candidate;
    foreach ($plugins as $consumer) {
      $constraint = $consumer->dependencyDefinitions()['required'][$candidate->handle()] ?? null;
      if ($consumer->handle() !== $candidate->handle() && $constraint !== null && $this->configured($consumer->handle()) && ! PluginDependencies::satisfies((string) $candidate->versionText(), $constraint)) {
        throw new RuntimeException(__('webblocks-cms::admin.plugin_dependencies.update_blocked', ['plugin' => $consumer->handle(), 'version' => $constraint]));
      }
    }
    $dependencies = new PluginDependencies;
    $dependencies->assertAcyclic($plugins, $candidate->handle());
    $registry = new PluginRegistry(array_fill_keys(array_filter(array_keys($plugins), fn ($handle) => $handle === $candidate->handle() || $this->enabled($handle)), true), respectRecoveryMode: false);
    foreach ($plugins as $plugin) {
      $registry->register($plugin);
    }
    $problems = $dependencies->problems($candidate, $plugins, fn ($handle) => $registry->isActive($handle));
    if ($problems !== []) {
      throw new RuntimeException(implode(' ', $problems));
    }
  }

  public function assertRemoval(string $handle): void
  {
    $dependents = (new PluginDependencies)->dependents($handle, $this->snapshot(), fn ($consumer) => $this->configured($consumer));
    if ($dependents !== []) {
      throw new RuntimeException(__('webblocks-cms::admin.plugin_dependencies.in_use', ['plugins' => implode(', ', $dependents)]));
    }
  }

  public function assertReplacement(array $manifest): void
  {
    $candidate = $this->definition($manifest);
    if ($this->configured($candidate->handle())) {
      $this->assertEnable($manifest);
    }
    foreach ($this->snapshot() as $consumer) {
      $constraint = $consumer->dependencyDefinitions()['required'][$candidate->handle()] ?? null;
      if ($constraint !== null && $this->configured($consumer->handle()) && ! PluginDependencies::satisfies((string) $candidate->versionText(), $constraint)) {
        throw new RuntimeException(__('webblocks-cms::admin.plugin_dependencies.update_blocked', ['plugin' => $consumer->handle(), 'version' => $constraint]));
      }
    }
  }
}
