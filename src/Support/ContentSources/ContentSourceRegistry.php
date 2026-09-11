<?php

namespace WebBlocks\Cms\Support\ContentSources;

use WebBlocks\Cms\Support\Plugins\PluginException;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;

class ContentSourceRegistry
{
  /** @var array<string, ContentSourceDefinition>|null */
  private ?array $sources = null;

  public function __construct(private readonly PluginRegistry $plugins) {}

  /** @return array<string, ContentSourceDefinition> */
  public function all(): array
  {
    if ($this->sources !== null) {
      return $this->sources;
    }

    $sources = [];

    foreach ($this->plugins->enabled() as $plugin) {
      foreach ($plugin->contentSourceDefinitions() as $source) {
        if (isset($sources[$source->handle()])) {
          throw new PluginException("Duplicate content source [{$source->handle()}].");
        }

        $sources[$source->handle()] = $source;
      }
    }

    ksort($sources);

    return $this->sources = $sources;
  }

  public function find(string $handle): ?ContentSourceDefinition
  {
    return $this->all()[$handle] ?? null;
  }

  /** @return array<string, ContentSourceDefinition> */
  public function collections(): array
  {
    return array_filter($this->all(), fn (ContentSourceDefinition $source): bool => $source->isCollection());
  }
}
