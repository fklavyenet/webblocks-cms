<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;

/**
 * Mirrors plugin-declared block types into the database block type catalog.
 *
 * Block pickers read `wbcms_block_types`, and `PluginBlockCatalog` only filters
 * that list: it hides a plugin's blocks while the plugin is disabled, but it
 * cannot add rows. Without this syncer a plugin could declare a block type,
 * ship both of its views, and still leave an editor with no way to place it.
 *
 * Rows are written for every installed plugin, enabled or not. A disabled
 * plugin's blocks stay out of pickers through the catalog filter, and blocks
 * already placed on a page still need their type row to resolve.
 */
class PluginBlockTypeCatalogSyncer
{
  public function __construct(
    private readonly PluginRegistry $plugins,
    private readonly PluginBlockCatalog $catalog,
    private readonly CoreBlockTypeCatalogSyncer $coreBlockTypes,
  ) {}

  /**
   * @return array{created: int, updated: int, unchanged: int, skipped: int}
   */
  public function sync(bool $dryRun = false): array
  {
    $summary = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0];
    $definitions = $this->definitions();

    if ($definitions === []) {
      return $summary;
    }

    if (! Schema::hasTable((new BlockType)->getTable())) {
      $summary['skipped'] = count($definitions);

      return $summary;
    }

    $coreSlugs = $this->coreBlockTypes->slugs();

    foreach ($definitions as $definition) {
      /*
       * A plugin handle always carries a namespace, so its catalog slug cannot
       * collide with a core slug by accident. Refusing to write anyway keeps a
       * malformed plugin from silently rewriting a shipped core block type.
       */
      if (in_array($definition['slug'], $coreSlugs, true)) {
        $summary['skipped']++;

        continue;
      }

      $blockType = BlockType::query()->where('slug', $definition['slug'])->first();

      if (! $blockType) {
        $summary['created']++;

        if (! $dryRun) {
          BlockType::query()->create($definition);
        }

        continue;
      }

      $changes = [];

      foreach ($this->updatableColumns() as $column) {
        if ($blockType->{$column} !== $definition[$column]) {
          $changes[$column] = $definition[$column];
        }
      }

      if ($changes === []) {
        $summary['unchanged']++;

        continue;
      }

      $summary['updated']++;

      if (! $dryRun) {
        $blockType->forceFill($changes)->save();
      }
    }

    return $summary;
  }

  /**
   * Catalog rows for every block type declared by an installed plugin.
   *
   * @return array<int, array<string, mixed>>
   */
  public function definitions(): array
  {
    $definitions = [];

    foreach ($this->plugins->all() as $plugin) {
      foreach ($this->catalog->blockTypeDefinitionsFor($plugin) as $blockType) {
        $slug = $this->catalog->catalogSlugForDefinition($blockType);

        if ($slug === '' || isset($definitions[$slug])) {
          continue;
        }

        $definitions[$slug] = $this->rowFor($slug, $blockType);
      }
    }

    return array_values($definitions);
  }

  /**
   * @return array<string, mixed>
   */
  private function rowFor(string $slug, PluginBlockTypeDefinition $blockType): array
  {
    $metadata = $blockType->metadataValues();
    $label = trim($blockType->labelText());
    $category = is_string($metadata['category'] ?? null) ? trim((string) $metadata['category']) : '';

    return [
      'slug' => $slug,
      'name' => $label !== '' ? $label : Str::headline(str_replace('-', ' ', $slug)),
      'description' => $blockType->descriptionText(),
      /*
       * Picker tabs are keyed by category, and a category nothing recognizes
       * leaves the block reachable only from "All". Content is the honest
       * default for a plugin block; a plugin that knows better says so in its
       * block metadata.
       */
      'category' => $category !== '' ? $category : 'content',
      'source_type' => 'static',
      'is_system' => false,
      'is_container' => (bool) ($metadata['is_container'] ?? false),
      'sort_order' => (int) ($metadata['sort_order'] ?? 0),
      'status' => 'published',
    ];
  }

  /**
   * Columns a re-sync is allowed to correct on an existing row.
   *
   * Category, sort order and status are deliberately absent: an operator who
   * retabbed a block, reordered it, or set it to draft to hide it made a
   * curation decision, and repairing the catalog should not undo it. The
   * columns listed here describe what the block *is*, which only the plugin
   * gets to say.
   *
   * @return array<int, string>
   */
  private function updatableColumns(): array
  {
    return [
      'name',
      'description',
      'source_type',
      'is_system',
      'is_container',
    ];
  }
}
