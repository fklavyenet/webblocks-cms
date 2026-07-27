<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Collection;

class PluginBlockCatalog
{
  /**
   * Enabled plugin block definitions keyed by catalog slug.
   *
   * `PluginRegistry::enabled()` deep-clones every definition it returns, which
   * is fine for an admin screen and much too expensive once per block on a
   * rendered page. `PluginRuntimeRefresher` forgets this singleton when a
   * plugin is installed, enabled, or updated, so the memo cannot go stale.
   *
   * @var array<string, PluginBlockTypeDefinition>|null
   */
  private ?array $enabledDefinitionsBySlug = null;

  public function __construct(
    private readonly PluginRegistry $plugins,
  ) {}

  public function enabledDefinitionForCatalogSlug(string $slug): ?PluginBlockTypeDefinition
  {
    if ($this->enabledDefinitionsBySlug === null) {
      $definitions = [];

      foreach ($this->plugins->enabled() as $plugin) {
        foreach ($this->blockTypeDefinitionsFor($plugin) as $blockType) {
          $definitions[$this->catalogSlugForDefinition($blockType)] = $blockType;
        }
      }

      $this->enabledDefinitionsBySlug = $definitions;
    }

    return $this->enabledDefinitionsBySlug[$slug] ?? null;
  }

  public function catalogSlugForHandle(string $handle): string
  {
    return str_replace('::', '-', strtolower(trim($handle)));
  }

  public function isPluginCatalogSlug(string $slug): bool
  {
    return in_array($slug, $this->allCatalogSlugs(), true);
  }

  public function isEnabledCatalogSlug(string $slug): bool
  {
    return in_array($slug, $this->enabledCatalogSlugs(), true);
  }

  /**
   * @param  Collection<int, mixed>  $blockTypes
   * @return Collection<int, mixed>
   */
  public function filterDiscoverableBlockTypes(Collection $blockTypes): Collection
  {
    $all = $this->allCatalogSlugs();

    if ($all === []) {
      return $blockTypes->values();
    }

    $enabled = $this->enabledCatalogSlugs();

    return $blockTypes
      ->reject(fn (mixed $blockType): bool => in_array((string) ($blockType->slug ?? ''), $all, true)
        && ! in_array((string) ($blockType->slug ?? ''), $enabled, true))
      ->values();
  }

  /**
   * @return array<int, string>
   */
  public function allCatalogSlugs(): array
  {
    return $this->catalogSlugsFromPlugins($this->plugins->all());
  }

  /**
   * @return array<int, string>
   */
  public function enabledCatalogSlugs(): array
  {
    return $this->catalogSlugsFromPlugins($this->plugins->enabled());
  }

  /**
   * @param  array<string, PluginDefinition>  $plugins
   * @return array<int, string>
   */
  private function catalogSlugsFromPlugins(array $plugins): array
  {
    $slugs = [];

    foreach ($plugins as $plugin) {
      foreach ($this->blockTypeDefinitionsFor($plugin) as $blockType) {
        $slugs[] = $this->catalogSlugForDefinition($blockType);
      }
    }

    return array_values(array_unique(array_filter($slugs)));
  }

  /**
   * @return array<int, PluginBlockTypeDefinition>
   */
  private function blockTypeDefinitionsFor(PluginDefinition $plugin): array
  {
    $definitions = array_values($plugin->blockTypeDefinitions());

    foreach ($plugin->blockPackDefinitions() as $blockPack) {
      $definitions = [...$definitions, ...array_values($blockPack->blockTypeDefinitions())];
    }

    return $definitions;
  }

  private function catalogSlugForDefinition(PluginBlockTypeDefinition $blockType): string
  {
    $metadata = $blockType->metadataValues();
    $catalogSlug = $metadata['catalog_slug'] ?? null;

    if (is_string($catalogSlug) && trim($catalogSlug) !== '') {
      return trim($catalogSlug);
    }

    return $this->catalogSlugForHandle($blockType->handle());
  }
}
