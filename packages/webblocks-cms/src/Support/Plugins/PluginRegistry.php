<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Console\Command;
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
    private readonly ?PluginCompatibility $compatibility = null,
  ) {}

  public function register(PluginDefinition $plugin): self
  {
    if (isset($this->plugins[$plugin->handle()])) {
      throw PluginException::duplicateHandle($plugin->handle());
    }

    $this->assertUniqueDatabasePrefix($plugin);
    $this->assertValidCommandNames($plugin);

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
      fn (PluginDefinition $plugin): bool => $this->isActive($plugin->handle())
    ));
  }

  /**
   * @return array<string, PluginDefinition>
   */
  public function disabled(): array
  {
    return $this->clonedPlugins(array_filter(
      $this->plugins,
      fn (PluginDefinition $plugin): bool => ! $this->isActive($plugin->handle())
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
    return $this->isConfiguredEnabled($handle) && $this->isCompatible($handle);
  }

  public function isActive(string $handle): bool
  {
    return $this->isEnabled($handle);
  }

  public function isConfiguredEnabled(string $handle): bool
  {
    if ($this->useLiveConfig) {
      if ((bool) config("webblocks-plugins.enabled.{$handle}", false)) {
        return true;
      }

      $plugin = $this->plugins[$handle] ?? null;

      if ($plugin?->installPathValue() !== null && app()->bound(InstalledPluginRepository::class)) {
        return app(InstalledPluginRepository::class)->enabledVersion($handle) === $plugin->versionText();
      }

      return false;
    }

    return (bool) ($this->enabledConfig[$handle] ?? false);
  }

  public function isCompatible(string $handle): bool
  {
    $plugin = $this->plugins[$handle] ?? null;

    if ($plugin === null) {
      return false;
    }

    return $this->compatibility()->isCompatible($plugin);
  }

  public function incompatibilityMessage(string $handle): ?string
  {
    $plugin = $this->plugins[$handle] ?? null;

    return $plugin === null ? null : $this->compatibility()->incompatibilityMessage($plugin);
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
      ->map(fn (PluginDefinition $plugin): array => $this->summaryFor($plugin))
      ->sortBy('label')
      ->values()
      ->all();
  }

  /**
   * @return array<string, mixed>
   */
  private function summaryFor(PluginDefinition $plugin): array
  {
    $configuredEnabled = $this->isConfiguredEnabled($plugin->handle());
    $compatible = $this->isCompatible($plugin->handle());
    $summary = $plugin->toArray($this->isEnabled($plugin->handle()));

    return array_merge($summary, [
      'configured_enabled' => $configuredEnabled,
      'compatible' => $compatible,
      'lifecycle_status' => $this->lifecycleStatus($configuredEnabled, $compatible),
      'incompatibility_message' => $this->incompatibilityMessage($plugin->handle()),
    ]);
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

  private function lifecycleStatus(bool $configuredEnabled, bool $compatible): string
  {
    if (! $compatible) {
      return 'incompatible';
    }

    return $configuredEnabled ? 'enabled' : 'disabled';
  }

  private function compatibility(): PluginCompatibility
  {
    return $this->compatibility ?? new PluginCompatibility;
  }

  private function assertUniqueDatabasePrefix(PluginDefinition $plugin): void
  {
    foreach ($this->plugins as $registered) {
      if ($registered->databasePrefixValue() === $plugin->databasePrefixValue()) {
        throw PluginException::duplicateDatabasePrefix($plugin->databasePrefixValue());
      }
    }
  }

  private function assertValidCommandNames(PluginDefinition $plugin): void
  {
    $registeredNames = [];

    foreach ($this->plugins as $registered) {
      foreach ($registered->commandClasses() as $command) {
        $name = $this->commandName($command);

        if ($name !== null) {
          $registeredNames[$name] = true;
        }
      }
    }

    foreach ($plugin->commandClasses() as $command) {
      $name = $this->commandName($command);

      if ($name === null) {
        continue;
      }

      if (! str_starts_with($name, $plugin->handle().':')) {
        throw PluginException::invalidCommandName($plugin->handle(), $name);
      }

      if (isset($registeredNames[$name])) {
        throw PluginException::duplicateCommandName($name);
      }

      $registeredNames[$name] = true;
    }
  }

  private function commandName(string $command): ?string
  {
    if (! class_exists($command) || ! is_subclass_of($command, Command::class)) {
      return null;
    }

    return app($command)->getName();
  }
}
