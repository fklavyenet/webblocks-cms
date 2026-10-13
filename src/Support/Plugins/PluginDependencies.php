<?php

namespace WebBlocks\Cms\Support\Plugins;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use RuntimeException;

class PluginDependencies
{
  public static function normalize(mixed $declarations, string $owner): array
  {
    if (! is_array($declarations)) {
      throw new RuntimeException(__('webblocks-cms::admin.plugin_dependencies.invalid'));
    }
    $result = ['required' => [], 'optional' => []];
    if (array_diff(array_keys($declarations), array_keys($result)) !== []) {
      throw new RuntimeException(__('webblocks-cms::admin.plugin_dependencies.invalid'));
    }
    foreach ($result as $kind => $_) {
      if (isset($declarations[$kind]) && ! is_array($declarations[$kind])) {
        throw new RuntimeException(__('webblocks-cms::admin.plugin_dependencies.invalid'));
      }
      foreach ($declarations[$kind] ?? [] as $handle => $constraint) {
        if (! is_string($handle) || ! PluginDefinition::isValidHandle($handle) || $handle === $owner || ! is_string($constraint) || trim($constraint) === '') {
          throw new RuntimeException(__('webblocks-cms::admin.plugin_dependencies.invalid'));
        }
        try {
          (new VersionParser)->parseConstraints($constraint);
        } catch (\Throwable) {
          throw new RuntimeException(__('webblocks-cms::admin.plugin_dependencies.invalid'));
        }
        $result[$kind][$handle] = trim($constraint);
      }
    }
    if (array_intersect_key($result['required'], $result['optional']) !== []) {
      throw new RuntimeException(__('webblocks-cms::admin.plugin_dependencies.invalid'));
    }

    return $result;
  }

  public static function satisfies(string $version, string $constraint): bool
  {
    try {
    return $version !== '' && Semver::satisfies($version, $constraint);
    } catch (\Throwable) {
    return false;
    }
  }

  public function problems(PluginDefinition $plugin, array $plugins, callable $active, string $kind = 'required'): array
  {
    $problems = [];
    foreach ($plugin->dependencyDefinitions()[$kind] as $handle => $constraint) {
      $dependency = $plugins[$handle] ?? null;
      if (! $dependency || ! $active($handle) || ! self::satisfies((string) $dependency->versionText(), $constraint)) {
        $problems[] = __('webblocks-cms::admin.plugin_dependencies.'.($kind === 'optional' ? 'optional_unavailable' : 'unavailable'), ['plugin' => $handle, 'version' => $constraint]);
      }
    }

    return $problems;
  }

  public function dependents(string $handle, array $plugins, callable $active): array
  {
    return array_values(array_map(fn ($plugin) => $plugin->handle(), array_filter($plugins,
      fn ($plugin) => isset($plugin->dependencyDefinitions()['required'][$handle]) && $active($plugin->handle()))));
  }

  public function assertAcyclic(array $plugins, ?string $root = null): void
  {
    $visiting = [];
    $visited = [];
    $visit = function (string $handle) use (&$visit, &$visiting, &$visited, $plugins): void {
      if (isset($visiting[$handle])) {
        throw new RuntimeException(__('webblocks-cms::admin.plugin_dependencies.cycle'));
      }
      if (isset($visited[$handle]) || ! isset($plugins[$handle])) {
        return;
      }
      $visiting[$handle] = true;
      foreach (array_keys($plugins[$handle]->dependencyDefinitions()['required']) as $dependency) {
        $visit($dependency);
      }
      unset($visiting[$handle]);
      $visited[$handle] = true;
    };
    foreach ($root === null ? array_keys($plugins) : [$root] as $handle) {
      $visit($handle);
    }
  }
}
