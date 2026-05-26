<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Contracts\Auth\Authenticatable;
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
   * @return array<int, PluginDashboardWidget>
   */
  public function dashboardWidgets(?Authenticatable $user = null): array
  {
    $widgets = [];

    foreach ($this->enabled() as $plugin) {
      foreach ($plugin->dashboardWidgetDefinitions() as $widget) {
        if (! $this->userCanView($user, $widget->permissionName())) {
          continue;
        }

        if (isset($widgets[$widget->key()])) {
          throw PluginException::duplicateExtensionKey($widget->key());
        }

        $widgets[$widget->key()] = $widget;
      }
    }

    usort($widgets, fn (PluginDashboardWidget $left, PluginDashboardWidget $right): int => $left->sortOrder() <=> $right->sortOrder());

    return $widgets;
  }

  /**
   * @return array<int, PluginSystemCard>
   */
  public function systemCards(?Authenticatable $user = null): array
  {
    $cards = [];

    foreach ($this->enabled() as $plugin) {
      foreach ($plugin->systemCardDefinitions() as $card) {
        if (! $this->userCanView($user, $card->permissionName())) {
          continue;
        }

        if (isset($cards[$card->key()])) {
          throw PluginException::duplicateExtensionKey($card->key());
        }

        $cards[$card->key()] = $card;
      }
    }

    usort($cards, fn (PluginSystemCard $left, PluginSystemCard $right): int => $left->sortOrder() <=> $right->sortOrder());

    return $cards;
  }

  /**
   * @return array<string, PluginBlockTypeDefinition>
   */
  public function pluginBlockTypes(): array
  {
    $blockTypes = [];

    foreach ($this->enabled() as $plugin) {
      foreach ($plugin->blockTypeDefinitions() as $blockType) {
        if (isset($blockTypes[$blockType->handle()])) {
          throw PluginException::duplicateBlockHandle($blockType->handle());
        }

        $blockTypes[$blockType->handle()] = $blockType;
      }

      foreach ($plugin->blockPackDefinitions() as $blockPack) {
        foreach ($blockPack->blockTypeDefinitions() as $blockType) {
          if (isset($blockTypes[$blockType->handle()])) {
            throw PluginException::duplicateBlockHandle($blockType->handle());
          }

          $blockTypes[$blockType->handle()] = $blockType;
        }
      }
    }

    ksort($blockTypes);

    return $blockTypes;
  }

  /**
   * @return array<string, PluginBlockPackDefinition>
   */
  public function pluginBlockPacks(): array
  {
    $blockPacks = [];

    foreach ($this->enabled() as $plugin) {
      foreach ($plugin->blockPackDefinitions() as $blockPack) {
        if (isset($blockPacks[$blockPack->namespace()])) {
          throw PluginException::invalidNamespace($blockPack->namespace());
        }

        $blockPacks[$blockPack->namespace()] = $blockPack;
      }
    }

    ksort($blockPacks);

    return $blockPacks;
  }

  /**
   * @return array<int, PluginPublicAsset>
   */
  public function publicAssets(?string $location = null): array
  {
    $assets = [];

    foreach ($this->enabled() as $plugin) {
      foreach ($plugin->publicAssetDefinitions() as $asset) {
        if ($location !== null && $asset->location() !== $location) {
          continue;
        }

        if (isset($assets[$asset->handle()])) {
          throw PluginException::duplicateAssetHandle($asset->handle());
        }

        $assets[$asset->handle()] = $asset;
      }
    }

    return array_values($assets);
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

  private function userCanView(?Authenticatable $user, ?string $permission): bool
  {
    if ($permission === null) {
      return true;
    }

    return (bool) $user?->can($permission);
  }
}
