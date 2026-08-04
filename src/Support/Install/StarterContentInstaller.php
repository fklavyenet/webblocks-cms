<?php

namespace WebBlocks\Cms\Support\Install;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use JsonException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockPayloadWriter;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Support\WebBlocks;

/**
 * Fills a freshly provisioned page with the shipped starter blocks.
 *
 * A fresh install used to serve an empty shell at `/`, which says nothing
 * about what the CMS does and gives a new admin nothing to edit. The starter
 * page is ordinary content — real blocks, real translations — so the first
 * thing a new user opens in the editor is a working page they can change.
 *
 * Two rules keep it safe on a live install: it writes only into a page that
 * has no blocks at all, and it never throws over its own content. A missing,
 * unreadable, or partly unsupported blueprint downgrades to a skipped result
 * the caller can report; it does not fail an install.
 */
class StarterContentInstaller
{
  public const SCHEMA = 'webblocks.cms.starter-content.v1';

  private const BLUEPRINT_NAME = 'home';

  public function __construct(
    private readonly BlockPayloadWriter $blockPayloadWriter,
    private readonly StarterMediaImporter $starterMediaImporter,
  ) {}

  public function enabled(): bool
  {
    return (bool) config('webblocks-cms.install.starter_content', true);
  }

  public function install(Page $page): StarterContentResult
  {
    if (! $this->enabled()) {
      return StarterContentResult::skipped('Starter content is disabled by configuration.');
    }

    if ($page->blocks()->exists()) {
      return StarterContentResult::skipped('The page already has blocks.');
    }

    $locale = Locale::query()->where('is_default', true)->where('is_enabled', true)->first()
      ?? Locale::query()->where('is_enabled', true)->orderBy('id')->first();

    if (! $locale) {
      return StarterContentResult::skipped('No enabled locale is available to write starter content in.');
    }

    $blueprintPath = $this->blueprintPath((string) $locale->code);
    $blueprint = $blueprintPath === null ? null : $this->readBlueprint($blueprintPath);

    if ($blueprint === null) {
      return StarterContentResult::skipped('No usable starter content blueprint was found.');
    }

    // Imported before the transaction: the file copy is not covered by it, and
    // a block that cannot get its media should still be written without one.
    $mediaIds = $this->importBlueprintMedia($blueprint, dirname((string) $blueprintPath));

    $created = 0;
    $skippedBlockTypes = [];

    $this->withMassAssignmentProtection(function () use ($page, $blueprint, $locale, $mediaIds, &$created, &$skippedBlockTypes): void {
      PublicSearchIndexer::coalescing(function () use ($page, $blueprint, $locale, $mediaIds, &$created, &$skippedBlockTypes): void {
        DB::transaction(function () use ($page, $blueprint, $locale, $mediaIds, &$created, &$skippedBlockTypes): void {
          foreach ($blueprint['slots'] as $slotSlug => $nodes) {
            $slotType = SlotType::query()->where('slug', $slotSlug)->first();

            if (! $slotType || ! $page->slots()->where('slot_type_id', $slotType->id)->exists()) {
              continue;
            }

            foreach (array_values($nodes) as $index => $node) {
              $this->createBlock($page, $slotType, $node, (string) $locale->code, null, $index, $mediaIds, $created, $skippedBlockTypes);
            }
          }
        });
      });
    });

    if ($created === 0) {
      return StarterContentResult::skipped('The starter content blueprint produced no blocks for this page.');
    }

    return StarterContentResult::installed($created, $skippedBlockTypes);
  }

  /**
   * @param  array<string, int>  $mediaIds
   * @param  array<int, string>  $skippedBlockTypes
   */
  private function createBlock(Page $page, SlotType $slotType, mixed $node, string $localeCode, ?Block $parent, int $sortOrder, array $mediaIds, int &$created, array &$skippedBlockTypes): void
  {
    if (! is_array($node)) {
      return;
    }

    $typeSlug = trim((string) ($node['type'] ?? ''));
    $blockType = $typeSlug === ''
      ? null
      : BlockType::query()->where('slug', $typeSlug)->where('status', 'published')->first();

    if (! $blockType) {
      // Skip the whole subtree. Re-parenting orphans onto the grandparent
      // would silently produce a layout the blueprint never described.
      $skippedBlockTypes[] = $typeSlug !== '' ? $typeSlug : '(missing type)';

      return;
    }

    $settings = is_array($node['settings'] ?? null) ? $node['settings'] : [];
    $translations = is_array($node['translations'] ?? null) ? $node['translations'] : [];

    $data = [
      'page_id' => $page->id,
      'parent_id' => $parent?->id,
      'block_type_id' => $blockType->id,
      'type' => $blockType->slug,
      'source_type' => $blockType->source_type ?: 'static',
      'slot_type_id' => $slotType->id,
      'slot' => $slotType->slug,
      'sort_order' => $sortOrder,
      'status' => 'published',
      'is_system' => (bool) $blockType->is_system,
      'settings' => $settings === [] ? null : json_encode($settings, JSON_UNESCAPED_SLASHES),
      'variant' => $node['variant'] ?? ($settings['variant'] ?? null),
      'url' => $settings['url'] ?? null,
    ];

    $mediaName = trim((string) ($node['media'] ?? ''));

    if ($mediaName !== '' && isset($mediaIds[$mediaName])) {
      $data['media_id'] = $mediaIds[$mediaName];
    }

    // The writer picks the fields its family knows, so unsupported copy in a
    // customized blueprint is ignored rather than written to the wrong column.
    // Structural keys are never overwritten: a mistyped translation field must
    // not be able to change the block's slot, status, or parent.
    foreach ($translations as $field => $value) {
      if (! is_string($field) || array_key_exists($field, $data)) {
        continue;
      }

      if (is_string($value) || $value === null) {
        $data[$field] = $value;
      }
    }

    $block = $this->blockPayloadWriter->save(new Block, $page, $data, $localeCode);
    $created++;

    $children = is_array($node['children'] ?? null) ? array_values($node['children']) : [];

    foreach ($children as $index => $child) {
      $this->createBlock($page, $slotType, $child, $localeCode, $block, $index, $mediaIds, $created, $skippedBlockTypes);
    }
  }

  /**
   * Imports every image a blueprint names, once, keyed by its file name.
   *
   * A blueprint references artwork by file name in a `media` key; the file
   * ships beside it under `media/`. Anything that fails to import is simply
   * absent from the map, and the block is written without it.
   *
   * @param  array{slots: array<string, array<int, mixed>>}  $blueprint
   * @return array<string, int>
   */
  private function importBlueprintMedia(array $blueprint, string $blueprintDirectory): array
  {
    $names = [];

    $collect = function (mixed $node) use (&$collect, &$names): void {
      if (! is_array($node)) {
        return;
      }

      $name = trim((string) ($node['media'] ?? ''));

      if ($name !== '' && ! in_array($name, $names, true)) {
        $names[] = $name;
      }

      foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
        $collect($child);
      }
    };

    foreach ($blueprint['slots'] as $nodes) {
      foreach ($nodes as $node) {
        $collect($node);
      }
    }

    $mediaIds = [];

    foreach ($names as $name) {
      // Blueprint-owned file names only: a path segment here would let a
      // customized blueprint read files from outside its own directory.
      if ($name !== basename($name)) {
        continue;
      }

      $media = $this->starterMediaImporter->import(
        $blueprintDirectory.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.$name,
        WebBlocks::name(),
      );

      if ($media) {
        $mediaIds[$name] = $media->id;
      }
    }

    return $mediaIds;
  }

  /**
   * A block payload carries translation-owned copy such as `eyebrow` next to
   * the block's own columns, and $fillable is what keeps those keys off the
   * `wbcms_blocks` row. Seeders run with mass assignment disabled, so the
   * writes have to restore it or the insert names columns that do not exist.
   */
  private function withMassAssignmentProtection(callable $callback): void
  {
    $wasUnguarded = Model::isUnguarded();

    if ($wasUnguarded) {
      Model::reguard();
    }

    try {
      $callback();
    } finally {
      if ($wasUnguarded) {
        Model::unguard();
      }
    }
  }

  /**
   * @return array{slots: array<string, array<int, mixed>>}|null
   */
  private function readBlueprint(string $path): ?array
  {
    $contents = @file_get_contents($path);

    if ($contents === false) {
      return null;
    }

    try {
      $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
      return null;
    }

    if (! is_array($decoded) || ($decoded['schema'] ?? null) !== self::SCHEMA) {
      return null;
    }

    $slots = $decoded['slots'] ?? null;

    if (! is_array($slots) || $slots === []) {
      return null;
    }

    $normalized = [];

    foreach ($slots as $slotSlug => $nodes) {
      if (is_string($slotSlug) && is_array($nodes) && $nodes !== []) {
        $normalized[$slotSlug] = array_values($nodes);
      }
    }

    return $normalized === [] ? null : ['slots' => $normalized];
  }

  private function blueprintPath(string $localeCode): ?string
  {
    $directory = trim((string) config('webblocks-cms.install.starter_content_path', ''));
    $directory = $directory !== '' ? rtrim($directory, '/\\') : $this->packageBlueprintDirectory();
    $localeCode = strtolower(trim($localeCode));

    $candidates = [];

    if ($localeCode !== '') {
      $candidates[] = self::BLUEPRINT_NAME.'.'.$localeCode.'.json';

      // "de-ch" falls back to the "de" blueprint before the shipped default.
      if (str_contains($localeCode, '-')) {
        $candidates[] = self::BLUEPRINT_NAME.'.'.explode('-', $localeCode)[0].'.json';
      }
    }

    $candidates[] = self::BLUEPRINT_NAME.'.json';

    foreach ($candidates as $candidate) {
      $path = $directory.DIRECTORY_SEPARATOR.$candidate;

      if (is_file($path) && is_readable($path)) {
        return $path;
      }
    }

    return null;
  }

  private function packageBlueprintDirectory(): string
  {
    return dirname(__DIR__, 3).'/database/content/starter';
  }
}
