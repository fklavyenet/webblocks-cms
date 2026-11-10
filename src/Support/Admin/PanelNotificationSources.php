<?php

namespace WebBlocks\Cms\Support\Admin;

use Generator;
use InvalidArgumentException;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;

final class PanelNotificationSources
{
  /** @var array<string, array<class-string<PanelNotificationSource>, true>> */
  private array $sources = [];

  /** @param class-string<PanelNotificationSource> $source */
  public function register(string $plugin, string $source): void
  {
    if (! is_subclass_of($source, PanelNotificationSource::class)) {
      throw new InvalidArgumentException('Invalid panel notification source.');
    }

    $this->sources[$plugin][$source] = true;
  }

  /** @return Generator<int, array{PluginDefinition, class-string<PanelNotificationSource>}> */
  public function active(): Generator
  {
    $plugins = app(PluginRegistry::class);
    foreach ($this->sources as $handle => $sources) {
      $plugin = $plugins->get($handle);
      if ($plugin === null || ! $plugins->isActive($handle)) {
        continue;
      }

      foreach (array_keys($sources) as $source) {
        yield [$plugin, $source];
      }
    }
  }
}
