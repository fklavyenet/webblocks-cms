<?php

namespace WebBlocks\Cms\Support\Plugins;

/**
 * Aggregates enabled plugins' internal API capability declarations so a single
 * shared CMS API token can be granted plugin capabilities. Only enabled plugins
 * contribute, so a capability is grantable exactly when its plugin is active.
 */
class PluginApiCapabilityRegistrar
{
  public function __construct(
    private readonly PluginRegistry $plugins,
  ) {}

  /**
   * All capability names contributed by enabled plugins.
   *
   * @return list<string>
   */
  public function names(): array
  {
    $names = [];

    foreach ($this->capabilityMaps() as $capabilities) {
      foreach (array_keys($capabilities) as $name) {
        $names[] = $name;
      }
    }

    return array_values(array_unique($names));
  }

  /**
   * Capability name => human label for enabled plugins.
   *
   * @return array<string, string>
   */
  public function labels(): array
  {
    $labels = [];

    foreach ($this->capabilityMaps() as $capabilities) {
      foreach ($capabilities as $name => $label) {
        $labels[$name] = is_string($label) && $label !== '' ? $label : $name;
      }
    }

    return $labels;
  }

  /**
   * Token-UI preset groups contributed by enabled plugins.
   *
   * @return list<array{key: string, label: string, description: string, capabilities: list<string>}>
   */
  public function groups(): array
  {
    $groups = [];

    foreach ($this->plugins->enabled() as $plugin) {
      $definition = $plugin->apiCapabilityDefinition();
      $capabilities = $this->normalizeCapabilities($definition['capabilities'] ?? []);

      if ($capabilities === []) {
        continue;
      }

      $group = $definition['group'] ?? null;

      $groups[] = [
        'key' => is_array($group) && isset($group['key']) ? (string) $group['key'] : $plugin->handle(),
        'label' => is_array($group) && isset($group['label']) ? (string) $group['label'] : $plugin->labelText(),
        'description' => is_array($group) && isset($group['description']) ? (string) $group['description'] : '',
        'capabilities' => array_keys($capabilities),
      ];
    }

    return $groups;
  }

  /**
   * @return array<int, array<string, string>>
   */
  private function capabilityMaps(): array
  {
    $maps = [];

    foreach ($this->plugins->enabled() as $plugin) {
      $capabilities = $this->normalizeCapabilities($plugin->apiCapabilityDefinition()['capabilities'] ?? []);

      if ($capabilities !== []) {
        $maps[] = $capabilities;
      }
    }

    return $maps;
  }

  /**
   * @param  mixed  $capabilities
   * @return array<string, string>
   */
  private function normalizeCapabilities($capabilities): array
  {
    if (! is_array($capabilities)) {
      return [];
    }

    $normalized = [];

    foreach ($capabilities as $name => $label) {
      if (is_string($name) && $name !== '') {
        $normalized[$name] = is_string($label) ? $label : $name;
      }
    }

    return $normalized;
  }
}
