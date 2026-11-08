<?php

namespace WebBlocks\Cms\Support\Revisions;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Support\Relational\PersistentDocuments;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;

/** Restore writes share SQL routing data without sharing mutable model instances. */
final class RestoredIndexTargets
{
  private array $scopes = [];

  private array $creations = [];

  public function during(Closure $write): mixed
  {
    $connection = DB::connection();
    if ($connection->transactionLevel() === 0 || ! PublicSearchIndexer::isCoalescing()
      || $connection->getEventDispatcher() !== app('events')) {
      return $write();
    }
    $this->scopes[] = new RestoredIndexScope($connection);
    try {
      return $write();
    } finally {
      array_pop($this->scopes);
    }
  }

  public function creating(Block $block, Closure $save): bool
  {
    $scope = $this->scopes[array_key_last($this->scopes)] ?? null;
    if ($scope === null || $block->exists || $block->getConnection() !== $scope->connection) {
      return $save();
    }
    $this->creations[] = new RestoredIndexCreation($scope, $block);
    try {
      return $save();
    } finally {
      array_pop($this->creations);
    }
  }

  public function hasCreation(Block $block): bool
  {
    return ($this->creations[array_key_last($this->creations)]->model ?? null) === $block;
  }

  public function target(Block $block): ?Block
  {
    $creation = $this->creations[array_key_last($this->creations)] ?? null;
    if (! $this->valid($creation, $block)) {
      return $block->fresh('page');
    }
    $page = $this->page($block, $creation->scope, fn () => $this->valid($creation, $block));

    return $page === null ? $block->fresh('page') : (clone $block)->unsetRelations()->setRelation('page', $page);
  }

  /** The caller proves this restored block is still current before seeding a translation. */
  public function pageForTranslation(Block $block): ?Page
  {
    $scope = $this->scopes[array_key_last($this->scopes)] ?? null;
    if ($scope === null || ! $this->validScope($scope, $block)) {
      return null;
    }

    return $this->page($block, $scope, fn () => $this->validScope($scope, $block));
  }

  private function page(Block $block, RestoredIndexScope $scope, Closure $valid): ?Page
  {
    $pageId = (int) $block->page_id;
    $entry = $scope->pages[$pageId] ?? null;
    if ($entry === null || $entry->stamp !== $scope->epoch) {
      // The first page load may read metadata. Finish earlier new-owner writes
      // before taking the page read epoch, rather than invalidating our own load.
      app(PersistentDocuments::class)->flushRestoreMetadata($scope->connection);
      if (! $valid()) {
        return null;
      }
      $entry = new RestoredIndexPage;
      $scope->pages[$pageId] = $entry;
      $epoch = $scope->epoch;
      $page = $block->page()->first();
      if ($page === null || $epoch !== $scope->epoch || ! $entry->documentsKnown || ! $valid()) {
        return null;
      }
      $entry->blocked = $this->containsObject($page->settings);
      if ($epoch !== $scope->epoch || ! $valid()) {
        return null;
      }
      $entry->page = $page;
      $entry->stamp = $epoch;
    }
    if ($entry->blocked) {
      return null;
    }
    $page = clone $entry->page;
    $page->setRelation('translations', $entry->page->translations->map(fn ($row) => (clone $row)->unsetRelations()));

    return $page;
  }

  public function beforeDocuments(Connection $connection, array $pageIds): void
  {
    foreach ($this->scopes as $scope) {
      if ($scope->connection === $connection) {
        foreach ($pageIds as $id) {
          if (isset($scope->pages[$id])) {
            $scope->pages[$id]->documentsKnown = false;
          }
        }
      }
    }
  }

  public function rememberDocuments(Connection $connection, array $pageIds, Collection $documents): void
  {
    foreach ($this->scopes as $scope) {
      if ($scope->connection === $connection) {
        foreach ($pageIds as $id) {
          if (isset($scope->pages[$id])) {
            $scope->pages[$id]->documentIds = $documents->where('page_id', $id)->pluck('id')->all();
            $scope->pages[$id]->documentsKnown = true;
          }
        }
      }
    }
  }

  public function onQuery(QueryExecuted $event): void
  {
    if ($this->scopes === [] || (! str_contains($event->sql, ';') && preg_match('/^\s*(select|pragma|explain|show|describe)\b/i', $event->sql))) {
      return;
    }
    $prefix = preg_quote($event->connection->getTablePrefix().'wbcms_', '/');
    $simple = ! str_contains($event->sql, ';') && preg_match('/^\s*(insert\s+into|update|delete\s+from)\s+["`]?'.$prefix.'([a-z_]+)["`]?\s/i', $event->sql, $match);
    $table = $simple ? $match[2] : null;
    foreach ($this->scopes as $scope) {
      $changed = $scope->connection !== $event->connection || $this->changesPage($scope, $table, $event);
      if ($changed) {
        $scope->epoch++;
      }
      foreach ($this->creations as $creation) {
        if ($creation->scope !== $scope) {
          continue;
        }
        if ($changed) {
          $creation->invalid = true;
        }
        if ($table === 'blocks') {
          $row = $simple ? $this->insertRow($event) : null;
          if ($row === null || ++$creation->inserts !== 1 || ! isset($row['page_id'])) {
            $creation->invalid = true;

            continue;
          }
          $id = $row['id'] ?? $event->connection->getPdo()->lastInsertId();
          $creation->id = filter_var($id, FILTER_VALIDATE_INT) ?: null;
          $creation->pageId = filter_var($row['page_id'], FILTER_VALIDATE_INT) ?: null;
        }
      }
    }
  }

  public function invalidate(): void
  {
    foreach ($this->scopes as $scope) {
      $scope->epoch++;
    }
    foreach ($this->creations as $creation) {
      $creation->invalid = true;
    }
  }

  private function valid(?RestoredIndexCreation $creation, Block $block): bool
  {
    if ($creation === null || $creation->model !== $block || $creation->invalid || $creation->inserts !== 1
      || $creation->id === null || $creation->pageId === null || ! $block->exists || ! $block->wasRecentlyCreated
      || (int) $block->id !== $creation->id || (int) $block->page_id !== $creation->pageId
      || ($this->scopes[array_key_last($this->scopes)] ?? null) !== $creation->scope || ! PublicSearchIndexer::isCoalescing()) {
      return false;
    }

    return $this->validScope($creation->scope, $block);
  }

  private function validScope(RestoredIndexScope $scope, Block $block): bool
  {
    $connection = $block->getConnection();
    if (($this->scopes[array_key_last($this->scopes)] ?? null) !== $scope || ! PublicSearchIndexer::isCoalescing()
      || $scope->connection !== $connection || DB::connection() !== $connection || $connection->transactionLevel() === 0
      || $connection->getEventDispatcher() !== app('events') || $connection->getPdo() !== $scope->pdo
      || $connection->getDatabaseName() !== $scope->database || $connection->getTablePrefix() !== $scope->prefix
      || ! in_array($connection->getDriverName(), ['sqlite', 'mysql', 'mariadb'], true)) {
      return false;
    }
    foreach ([Block::class, Page::class, PageTranslation::class] as $class) {
      if ($class::getEventDispatcher() !== app('events') || ($class::getAllGlobalScopes()[$class] ?? []) !== []
        || app('events')->hasListeners('eloquent.retrieved: '.$class)) {
        return false;
      }
    }

    return true;
  }

  private function changesPage(RestoredIndexScope $scope, ?string $table, QueryExecuted $event): bool
  {
    if ($table === null) {
      return true;
    }
    if ($table === 'persistent_documents') {
      $rows = $this->insertValues($event);

      return $rows === null || $rows === [] || array_key_exists('id', $rows[0]) || array_key_exists('page_id', $rows[0]);
    }
    if (in_array($table, ['persistent_nodes', 'persistent_metadata', 'persistent_items', 'import_identity_maps', 'import_copied_files'], true)) {
      if ($scope->pages === []) {
        return false; // The first routing read still happens after this SQL.
      }
      $ids = $this->insertDocumentIds($event);
      if ($ids === null) {
        return true;
      }
      foreach ($scope->pages as $entry) {
        if (! $entry->documentsKnown || array_intersect($entry->documentIds, $ids) !== []) {
          return true;
        }
      }

      return false;
    }
    if ($table === 'blocks' || preg_match('/^(block_|setting_|snapshot_)/', $table)
      || in_array($table, ['page_revisions', 'shared_slot_revisions', 'revision_capture_events', 'public_search_index', 'page_list_cache_versions', 'navigation_cache_versions', 'seo_source_versions'], true)) {
      return false;
    }

    return true;
  }

  private function insertRow(QueryExecuted $event): ?array
  {
    $rows = $this->insertValues($event);

    return $rows !== null && count($rows) === 1 ? $rows[0] : null;
  }

  private function insertDocumentIds(QueryExecuted $event): ?array
  {
    $rows = $this->insertValues($event);
    if ($rows === null) {
      return null;
    }
    $ids = [];
    foreach ($rows as $row) {
      $id = filter_var($row['document_id'] ?? null, FILTER_VALIDATE_INT);
      if ($id === false || $id < 1) {
        return null;
      }
      $ids[] = $id;
    }

    return $ids;
  }

  private function insertValues(QueryExecuted $event): ?array
  {
    if (! array_is_list($event->bindings) || ! preg_match('/^\s*insert\s+into\s+\S+\s*\(([^)]+)\)\s*values\s*/i', $event->sql, $match)) {
      return null;
    }
    $columns = array_map(fn ($column) => trim($column, " \t\n\r\0\x0B\"`"), explode(',', $match[1]));
    $rowCount = intdiv(count($event->bindings), count($columns));
    $tuple = '('.implode(',', array_fill(0, count($columns), '?')).')';
    // Exact placeholder tuples avoid regex stack limits on driver-sized batches.
    $values = preg_replace('/\s+/', '', substr($event->sql, strlen($match[0])));
    if ($rowCount === 0 || count($event->bindings) % count($columns) !== 0
      || $values !== implode(',', array_fill(0, $rowCount, $tuple))) {
      return null;
    }

    return array_map(fn ($values) => array_combine($columns, $values), array_chunk($event->bindings, count($columns)));
  }

  private function containsObject(mixed $value): bool
  {
    if (is_object($value)) {
      return true;
    }
    foreach (is_array($value) ? $value : [] as $item) {
      if ($this->containsObject($item)) {
        return true;
      }
    }

    return false;
  }
}

/** @internal */
final class RestoredIndexScope
{
  public array $pages = [];

  public int $epoch = 0;

  public readonly mixed $pdo;

  public readonly ?string $database;

  public readonly string $prefix;

  public function __construct(public readonly Connection $connection)
  {
    $this->pdo = $connection->getPdo();
    $this->database = $connection->getDatabaseName();
    $this->prefix = $connection->getTablePrefix();
  }
}

/** @internal */
final class RestoredIndexCreation
{
  public bool $invalid = false;

  public int $inserts = 0;

  public ?int $id = null;

  public ?int $pageId = null;

  public function __construct(public readonly RestoredIndexScope $scope, public readonly Block $model) {}
}

/** @internal */
final class RestoredIndexPage
{
  public ?Page $page = null;

  public int $stamp = -1;

  public bool $documentsKnown = false;

  public array $documentIds = [];

  public bool $blocked = false;
}
