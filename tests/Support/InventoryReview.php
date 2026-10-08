<?php

declare(strict_types=1);

namespace WebBlocks\Cms\Tests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\Blocks\MobileBlockMedia;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeApiAuthoringPolicy;

/** Product-contract tooling. It never reads a documentation repository. */
final class InventoryReview
{
  public const DOCUMENT = 'resources/contracts/inventory.md';

  public const RECORD = 'resources/contracts/inventory-review.json';

  // Conservatively cover runtime behavior, editor controls, schemas and assets.
  // New files under these roots participate without changing a manual file list.
  public const SOURCE_ROOTS = ['src', 'config', 'routes', 'database', 'resources', 'public', 'stubs'];

  public static function sources(string $root): array
  {
    $sources = [];
    foreach (self::SOURCE_ROOTS as $directory) {
      if (! is_dir($root.'/'.$directory)) {
        throw new RuntimeException('Missing inventory source root: '.$directory);
      }
      $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, RecursiveDirectoryIterator::SKIP_DOTS));
      foreach ($files as $file) {
        if ($file->isLink()) {
          throw new RuntimeException('Inventory sources must not contain symlinks: '.substr($file->getPathname(), strlen($root) + 1));
        }
        if (! $file->isFile()) {
          continue;
        }
        $relative = substr($file->getPathname(), strlen($root) + 1);
        if (in_array($relative, [self::DOCUMENT, self::RECORD], true)) {
          continue;
        }
        if (preg_match('#(?:^|/)\.|(?:\.bak|~)$#', $relative) === 1) {
          continue;
        }
        $sources[$relative] = hash_file('sha256', $file->getPathname());
      }
    }
    $sources['composer.json'] = hash_file('sha256', $root.'/composer.json');
    ksort($sources);

    return $sources;
  }

  public static function fingerprint(array $sources): string
  {
    ksort($sources);

    return hash('sha256', json_encode($sources, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
  }

  public static function version(string $root): string
  {
    $source = (string) file_get_contents($root.'/src/Support/WebBlocks.php');
    if (preg_match("/\\bconst\\s+(?:string\\s+)?VERSION\\s*=\\s*'([^']+)'/", $source, $matches) !== 1) {
      throw new RuntimeException('Cannot read the product version.');
    }

    return $matches[1];
  }

  public static function read(string $root): array
  {
    if (! is_file($root.'/'.self::RECORD)) {
      throw new RuntimeException('Missing inventory review record. Review the contract and run composer inventory:review -- --reviewed --note="review summary".');
    }

    return json_decode((string) file_get_contents($root.'/'.self::RECORD), true, flags: JSON_THROW_ON_ERROR);
  }

  public static function errors(string $root): array
  {
    $record = self::read($root);
    $errors = [];
    if (($record['schema_version'] ?? null) !== 1 || ($record['document'] ?? null) !== self::DOCUMENT) {
      $errors[] = 'Invalid inventory review schema or document identity.';
    }
    if (($record['inventory_sha256'] ?? null) !== hash_file('sha256', $root.'/'.self::DOCUMENT)) {
      $errors[] = 'Inventory prose changed after its recorded review.';
    }
    if (($record['product_version'] ?? null) !== self::version($root)) {
      $errors[] = 'Inventory review belongs to a different product version.';
    }
    $sources = self::sources($root);
    if (($record['sources'] ?? null) !== $sources || ($record['source_sha256'] ?? null) !== self::fingerprint($sources)) {
      $previous = $record['sources'] ?? [];
      $changed = array_filter(array_unique([...array_keys($previous), ...array_keys($sources)]), fn ($path) => ($previous[$path] ?? null) !== ($sources[$path] ?? null));
      $errors[] = 'Runtime sources changed after inventory review: '.implode(', ', array_slice(array_values($changed), 0, 8)).'.';
    }
    if (! in_array($record['review_outcome'] ?? null, ['updated', 'no-authoring-impact'], true)
      || ! is_string($record['review_note'] ?? null) || ! self::safeNote($record['review_note'])) {
      $errors[] = 'Inventory review needs an explicit outcome and explanation.';
    }

    return array_merge($errors, self::documentErrors((string) file_get_contents($root.'/'.self::DOCUMENT), $record['core_surface'] ?? []));
  }

  public static function documentErrors(string $document, array $surface): array
  {
    $errors = [];
    preg_match_all('/^### `([^`]+)`/m', $document, $matches);
    $headings = $matches[1];
    foreach ($surface as $contract) {
      if (count(array_filter($headings, fn ($slug) => $slug === $contract['slug'])) !== 1) {
        $errors[] = 'Inventory must document the published core block exactly once: '.$contract['slug'];
      }
    }
    if ($surface === []) {
      $errors[] = 'Missing mechanical core authoring surface.';
    }
    if (preg_match('/The current published core catalog contains (\d+) rows:\R(.*?)(?=^## )/ms', $document, $catalog) !== 1) {
      $errors[] = 'Missing current published catalog table.';
    } else {
      preg_match_all('/`([a-z0-9_-]+)`/', $catalog[2], $handles);
      $listed = $handles[1];
      $expected = array_column($surface, 'slug');
      sort($listed);
      sort($expected);
      if ((int) $catalog[1] !== count($surface) || $listed !== $expected) {
        $errors[] = 'Current inventory catalog count or handle list differs from the published core surface.';
      }
    }

    return $errors;
  }

  /** Uses real model helpers, not a second hand-maintained child/root policy. */
  public static function surface(): array
  {
    $policy = new BlockTypeApiAuthoringPolicy;
    $surface = [];
    foreach ((new CoreBlockTypeCatalogSyncer)->definitions() as $definition) {
      if ($definition['status'] !== 'published') {
        continue;
      }
      $block = new Block(['type' => $definition['slug']]);
      $block->setRelation('blockType', new BlockType($definition));
      $surface[] = [
        'slug' => $definition['slug'],
        'supports_children' => $block->canAcceptChildren(),
        'allowed_child_types' => $block->allowedChildTypeSlugs(),
        'owns_public_root' => $block->ownsPublicRoot(),
        'api_writable' => $policy->isApiWritable($definition['slug']),
        'mobile_media' => MobileBlockMedia::supports($definition['slug']),
      ];
    }
    usort($surface, fn ($a, $b) => strcmp($a['slug'], $b['slug']));

    return $surface;
  }

  public static function record(string $root, string $note, ?string $noImpact = null): array
  {
    if (! self::safeNote($note)) {
      throw new RuntimeException('Provide a short review summary without private paths.');
    }
    $previous = is_file($root.'/'.self::RECORD) ? self::read($root) : [];
    $sources = self::sources($root);
    $inventoryHash = hash_file('sha256', $root.'/'.self::DOCUMENT);
    if ($previous !== [] && ($previous['source_sha256'] ?? null) !== self::fingerprint($sources)
      && ($previous['inventory_sha256'] ?? null) === $inventoryHash && ($noImpact === null || trim($noImpact) === '')) {
      throw new RuntimeException('Runtime changed but inventory prose did not. Update the contract, or explicitly explain --no-authoring-impact="reason" after reviewing the change.');
    }
    if ($noImpact !== null && ! self::safeNote($noImpact)) {
      throw new RuntimeException('The no-impact explanation must be nonempty and contain no private paths.');
    }

    $surface = self::surface();
    $errors = self::documentErrors((string) file_get_contents($root.'/'.self::DOCUMENT), $surface);
    if ($errors !== []) {
      throw new RuntimeException(implode(PHP_EOL, $errors));
    }
    if ($sources !== self::sources($root) || $inventoryHash !== hash_file('sha256', $root.'/'.self::DOCUMENT)) {
      throw new RuntimeException('Sources changed during inventory review. Retry after finishing the source changes.');
    }

    return [
      'schema_version' => 1,
      'document' => self::DOCUMENT,
      'product_version' => self::version($root),
      'inventory_sha256' => $inventoryHash,
      'source_sha256' => self::fingerprint($sources),
      'review_outcome' => $noImpact === null ? 'updated' : 'no-authoring-impact',
      'review_note' => $noImpact ?? $note,
      'sources' => $sources,
      'core_surface' => $surface,
    ];
  }

  private static function safeNote(string $note): bool
  {
    return trim($note) !== '' && strlen($note) <= 1000 && preg_match('~(?:^|[\s"\'(=:])/Users/~', $note) !== 1
      && preg_match('/(?:\bBearer\s+\S+|\bsk-[A-Za-z0-9_-]+|(?:token|secret|password|api[_-]?key)\s*[:=]\s*\S+)/i', $note) !== 1;
  }
}
