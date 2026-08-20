<?php

namespace WebBlocks\Cms\Support\Sites\ExportImport;

/**
 * The ordered phases of a site import, and which of them can stop mid-way.
 *
 * An import used to be one transaction inside one request. It is now a
 * sequence of committed steps, which needs two things this class holds: a
 * stable phase order to resume against, and, for the phases that walk a list
 * out of the package, the name of that list so a step can take a slice of it
 * and record how far it got.
 *
 * Phase order is a contract. Renaming or reordering a key invalidates the
 * cursor of every import that is mid-flight when the install updates, so add
 * new phases at the end of the group they belong to rather than renumbering.
 */
class SiteImportPlan
{
  /**
   * Phase key => the package list it walks, or null when it runs as one unit.
   *
   * Two orderings here are load-bearing rather than incidental:
   *
   * - `domains` runs LAST. A site is only reachable through a SiteDomain row
   *   (see SiteResolver), and Site carries no published flag, so writing the
   *   domain early would put a half-imported site on its real hostname. Last
   *   means an interrupted import is never addressable.
   * - `search_index` runs after all content and before domains. Every write
   *   before it happens with indexing deferred, so this is the one pass that
   *   builds the index.
   * - `site_branding` runs after `assets`. The site row is written long before
   *   the media it points at exists, so favicon and social image can only be
   *   rebound once the asset map is populated.
   */
  public const PHASES = [
    'catalogs' => null,
    'locales' => null,
    'site' => null,
    'site_locales' => null,
    'site_variables' => null,
    'asset_folders' => null,
    'assets' => 'media',
    'site_branding' => null,
    'pages' => 'pages',
    'page_assets' => 'page_assets',
    'site_public_assets' => null,
    'embedded_applications' => null,
    'shared_slots' => null,
    'page_slots' => 'page_slots',
    'blocks' => 'blocks',
    'block_parents' => 'blocks',
    'block_text_translations' => 'block_text_translations',
    'block_button_translations' => 'block_button_translations',
    'block_image_translations' => 'block_image_translations',
    'block_contact_form_translations' => 'block_contact_form_translations',
    'block_translation_storage' => 'blocks',
    'block_assets' => 'block_media',
    'gallery_translations' => 'block_gallery_item_translations',
    'shared_slot_assignments' => null,
    'navigation' => null,
    'search_index' => null,
    'domains' => null,
  ];

  /**
   * Rows a single slice takes on, per phase.
   *
   * The time budget cannot interrupt a slice, only decide whether to start
   * another, so the slice itself has to stay small enough to keep the step
   * responsive. Asset phases use a much smaller slice because each of their
   * rows extracts a file from the package and writes it to disk, which is
   * worth far more wall-clock than an insert.
   */
  public const CHUNK_SIZES = [
    'assets' => 25,
    'page_assets' => 25,
    'default' => 250,
  ];

  public static function chunkSize(string $phase): int
  {
    return self::CHUNK_SIZES[$phase] ?? self::CHUNK_SIZES['default'];
  }

  /**
   * Phases that must not be wrapped in a transaction.
   *
   * The index rebuild reads rows every earlier phase already committed and
   * writes a lot; holding a transaction open around it buys no atomicity and
   * only lengthens the window where another connection waits on its locks.
   */
  public const UNTRANSACTED_PHASES = ['search_index'];

  public static function needsTransaction(string $phase): bool
  {
    return ! in_array($phase, self::UNTRANSACTED_PHASES, true);
  }

  /**
   * @return list<string>
   */
  public static function keys(): array
  {
    return array_keys(self::PHASES);
  }

  public static function first(): string
  {
    return (string) array_key_first(self::PHASES);
  }

  public static function isKnown(?string $phase): bool
  {
    return $phase !== null && array_key_exists($phase, self::PHASES);
  }

  public static function listKey(string $phase): ?string
  {
    return self::PHASES[$phase] ?? null;
  }

  public static function isChunkable(string $phase): bool
  {
    return self::listKey($phase) !== null;
  }

  /**
   * The phase after this one, or null when the import is finished.
   */
  public static function next(string $phase): ?string
  {
    $keys = self::keys();
    $index = array_search($phase, $keys, true);

    if ($index === false) {
      return null;
    }

    return $keys[$index + 1] ?? null;
  }

  /**
   * How many units of work a phase holds for this package.
   *
   * A list phase counts its rows so the progress bar reflects the shape of the
   * package rather than the number of phases: a package is mostly blocks, and
   * counting phases would show 12/24 while three quarters of the rows remain.
   * A unit phase counts as one.
   *
   * @param  array<string, mixed>  $payload
   */
  public static function weight(string $phase, array $payload): int
  {
    $listKey = self::listKey($phase);

    if ($listKey === null) {
      return 1;
    }

    return count($payload[$listKey] ?? []);
  }

  /**
   * Total units across every phase, used as the progress denominator.
   *
   * @param  array<string, mixed>  $payload
   */
  public static function total(array $payload): int
  {
    $total = 0;

    foreach (self::keys() as $phase) {
      $total += self::weight($phase, $payload);
    }

    return $total;
  }

  /**
   * Units already behind the cursor, used as the progress numerator.
   *
   * @param  array<string, mixed>  $payload
   */
  public static function completedUnits(string $phase, int $offset, array $payload): int
  {
    $done = 0;

    foreach (self::keys() as $key) {
      if ($key === $phase) {
        return $done + min($offset, self::weight($key, $payload));
      }

      $done += self::weight($key, $payload);
    }

    return $done;
  }
}
