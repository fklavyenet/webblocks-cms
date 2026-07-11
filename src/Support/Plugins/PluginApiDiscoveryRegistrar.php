<?php

namespace WebBlocks\Cms\Support\Plugins;

/**
 * Aggregates enabled plugins' API discovery contributions so the internal API
 * discovery endpoint can advertise plugin-owned endpoints to AI agents. Because
 * only enabled plugins contribute, disabling a plugin removes its endpoints from
 * discovery — the description always reflects the live surface.
 */
class PluginApiDiscoveryRegistrar
{
  public function __construct(
    private readonly PluginRegistry $plugins,
  ) {}

  /**
   * Resource name => path map, merged into the discovery `_links`.
   *
   * @return array<string, string>
   */
  public function resources(): array
  {
    $resources = [];

    foreach ($this->contributions() as $discovery) {
      foreach (($discovery['resources'] ?? []) as $name => $path) {
        if (is_string($name) && is_string($path) && $name !== '' && $path !== '') {
          $resources[$name] = $path;
        }
      }
    }

    return $resources;
  }

  /**
   * OpenAPI path items keyed by path, merged into the schema.
   *
   * @return array<string, mixed>
   */
  public function openApiPaths(): array
  {
    $paths = [];

    foreach ($this->contributions() as $discovery) {
      foreach (($discovery['openapi_paths'] ?? []) as $path => $spec) {
        if (is_string($path) && $path !== '' && is_array($spec)) {
          $paths[$path] = $spec;
        }
      }
    }

    return $paths;
  }

  /**
   * Usage hints appended to recommended_next_steps.
   *
   * @return list<string>
   */
  public function guidance(): array
  {
    $guidance = [];

    foreach ($this->contributions() as $discovery) {
      foreach (($discovery['guidance'] ?? []) as $hint) {
        if (is_string($hint) && $hint !== '') {
          $guidance[] = $hint;
        }
      }
    }

    return $guidance;
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function contributions(): array
  {
    $contributions = [];

    foreach ($this->plugins->enabled() as $plugin) {
      $discovery = $plugin->apiDiscoveryDefinition();

      if ($discovery !== []) {
        $contributions[] = $discovery;
      }
    }

    return $contributions;
  }
}
