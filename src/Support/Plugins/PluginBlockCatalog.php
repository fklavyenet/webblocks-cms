<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Collection;

class PluginBlockCatalog
{
  public function __construct(
    private readonly PluginRegistry $plugins,
  ) {}

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
      foreach ($plugin->blockTypeDefinitions() as $blockType) {
        $slugs[] = $this->catalogSlugForDefinition($blockType);
      }

      foreach ($plugin->blockPackDefinitions() as $blockPack) {
        foreach ($blockPack->blockTypeDefinitions() as $blockType) {
          $slugs[] = $this->catalogSlugForDefinition($blockType);
        }
      }
    }

    return array_values(array_unique(array_filter($slugs)));
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
