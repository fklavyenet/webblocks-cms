<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Collection;

class PluginRegistry
{
  /** @var array<string, PluginDefinition> */
  private array $plugins = [];

  /**
   * @param  array<string, bool>  $enabledConfig
   */
  public function __construct(
    private readonly array $enabledConfig = [],
    private readonly bool $useLiveConfig = false,
  ) {}

  public function register(PluginDefinition $plugin): self
  {
    if (isset($this->plugins[$plugin->handle()])) {
      throw PluginException::duplicateHandle($plugin->handle());
    }

    $this->plugins[$plugin->handle()] = clone $plugin;

    return $this;
  }

  /**
   * @return array<string, PluginDefinition>
   */
  public function all(): array
  {
    return $this->clonedPlugins($this->plugins);
  }

  /**
   * @return array<string, PluginDefinition>
   */
  public function enabled(): array
  {
    return $this->clonedPlugins(array_filter(
      $this->plugins,
      fn (PluginDefinition $plugin): bool => $this->isEnabled($plugin->handle())
    ));
  }

  /**
   * @return array<string, PluginDefinition>
   */
  public function disabled(): array
  {
    return $this->clonedPlugins(array_filter(
      $this->plugins,
      fn (PluginDefinition $plugin): bool => ! $this->isEnabled($plugin->handle())
    ));
  }

  public function get(string $handle): ?PluginDefinition
  {
    return isset($this->plugins[$handle]) ? clone $this->plugins[$handle] : null;
  }

  public function has(string $handle): bool
  {
    return isset($this->plugins[$handle]);
  }

  public function isEnabled(string $handle): bool
  {
    if ($this->useLiveConfig) {
      return (bool) config("webblocks-plugins.enabled.{$handle}", false);
    }

    return (bool) ($this->enabledConfig[$handle] ?? false);
  }

  /**
   * @return array<int, array{plugin: PluginDefinition, item: PluginMenuItem}>
   */
  public function menuItems(): array
  {
    $items = [];

    foreach ($this->enabled() as $plugin) {
      foreach ($plugin->menuItems() as $item) {
        $items[] = [
          'plugin' => clone $plugin,
          'item' => clone $item,
        ];
      }
    }

    usort($items, fn (array $left, array $right): int => $left['item']->sortOrder() <=> $right['item']->sortOrder());

    return $items;
  }

  /**
   * @return array<string, array<string, PluginPermission>>
   */
  public function permissions(bool $enabledOnly = true): array
  {
    $plugins = $enabledOnly ? $this->enabled() : $this->plugins;
    $permissions = [];

    foreach ($plugins as $plugin) {
      $permissions[$plugin->handle()] = $this->clonedPermissions($plugin->permissionsList());
    }

    return $permissions;
  }

  /**
   * @return array<string, array<string, PluginPermission>>
   */
  public function disabledPermissions(): array
  {
    $permissions = [];

    foreach ($this->disabled() as $plugin) {
      $permissions[$plugin->handle()] = $this->clonedPermissions($plugin->permissionsList());
    }

    return $permissions;
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public function summaries(): array
  {
    return Collection::make($this->plugins)
      ->map(fn (PluginDefinition $plugin): array => $plugin->toArray($this->isEnabled($plugin->handle())))
      ->sortBy('label')
      ->values()
      ->all();
  }

  /**
   * @param  array<string, PluginDefinition>  $plugins
   * @return array<string, PluginDefinition>
   */
  private function clonedPlugins(array $plugins): array
  {
    return array_map(fn (PluginDefinition $plugin): PluginDefinition => clone $plugin, $plugins);
  }

  /**
   * @param  array<string, PluginPermission>  $permissions
   * @return array<string, PluginPermission>
   */
  private function clonedPermissions(array $permissions): array
  {
    return array_map(fn (PluginPermission $permission): PluginPermission => clone $permission, $permissions);
  }
}
