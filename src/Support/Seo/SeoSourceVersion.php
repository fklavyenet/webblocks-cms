<?php

namespace WebBlocks\Cms\Support\Seo;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use WeakMap;
use WebBlocks\Cms\Support\Database\ContentVersionUpdates;
use WebBlocks\Cms\Support\Pages\PublicMount;
use WebBlocks\Cms\Support\WebBlocks;

/** Conservative connection-wide generation: unrelated site edits may also stale a run. */
final class SeoSourceVersion
{
  private const TABLE = 'wbcms_seo_source_versions';

  private WeakMap $schemas;

  /** Only validated, non-content snapshot identities inside an explicit write. */
  private WeakMap $snapshotScopes;

  public function __construct()
  {
    $this->schemas = new WeakMap;
    $this->snapshotScopes = new WeakMap;
  }

  public function duringSnapshotDocument(Connection $connection, int $documentId, Closure $write): mixed
  {
    if ($connection->transactionLevel() === 0 || $connection->getEventDispatcher() !== app('events')) {
      return $write();
    }
    $scope = $this->snapshotScopes[$connection] ?? ['documents' => [], 'known' => [], 'generation' => 0, 'pdo' => $connection->getPdo(), 'database' => $connection->getDatabaseName(), 'prefix' => $connection->getTablePrefix()];
    $scope['documents'][$documentId] = ($scope['documents'][$documentId] ?? 0) + 1;
    $this->snapshotScopes[$connection] = $scope;
    try {
      return $write();
    } finally {
      $scope = $this->snapshotScopes[$connection];
      if (--$scope['documents'][$documentId] === 0) {
        unset($scope['documents'][$documentId], $scope['known'][$documentId]);
      }
      if ($scope['documents'] === []) {
        unset($this->snapshotScopes[$connection]);
      } else {
        $this->snapshotScopes[$connection] = $scope;
      }
    }
  }

  /** Different Laravel connections may address the same database or PDO. */
  public function forgetSnapshotOwners(): void
  {
    foreach ($this->snapshotScopes as $connection => $scope) {
      $scope['known'] = [];
      $scope['generation']++;
      $this->snapshotScopes[$connection] = $scope;
    }
  }

  public function onQuery(QueryExecuted $event): void
  {
    if (preg_match('/^\s*(select|pragma|explain|show|describe)\b/i', $event->sql)) {
      return;
    }
    // Clear ownership before the pending-generation shortcut, including when a
    // later current() call flushes that generation inside the same transaction.
    if (count($this->snapshotScopes) > 0) {
      $prefix = preg_quote($event->connection->getTablePrefix().'wbcms_', '/');
      // Only structural/typed row writes can retain the classification. Other
      // statements include owner changes, parent cascades and schema switches.
      if (str_contains($event->sql, 'wbcms_persistent_documents')
        || ! preg_match('/^\s*(?:insert\s+into|update|delete\s+from)\s+["`]?'.$prefix.'(?:persistent_nodes|snapshot_\w+)\b/i', $event->sql)) {
        $this->forgetSnapshotOwners();
      }
    }
    if (str_contains($event->sql, self::TABLE)) {
      if (preg_match('/\b(create|drop|alter)\b/i', $event->sql)) {
        unset($this->schemas[$event->connection]);
      }

      return;
    }
    $versions = app(ContentVersionUpdates::class);
    if ($versions->isPending($event->connection, self::TABLE)) {
      return;
    }
    $prefix = preg_quote($event->connection->getTablePrefix().'wbcms_', '/');
    if (! preg_match('/\b'.$prefix.'(?:pages|page_translations|page_slots|page_assets|page_access_rules|page_runtime_\w+|page_layout\w*|page_types|blocks|block_\w+|shared_slot\w*|navigation_\w+|sites|site_domains|site_locales|locales|media|media_\w+|assets|slot_types|layouts|layout_types|setting_\w+|persistent_\w+|automatic_page_\w+|embedded_applications|application_\w+|content_source\w*|plugin_\w+|system_settings|consent_\w+)\b/i', $event->sql)) {
      return;
    }
    if (preg_match('/\b'.$prefix.'persistent_\w+\b/i', $event->sql) && ! $this->contentDocumentWrite($event)) {
      return;
    }
    if ($this->schemas[$event->connection] ??= $event->connection->getSchemaBuilder()->hasTable(self::TABLE)) {
      $versions->invalidate($event->connection, self::TABLE);
    }
  }

  /** Shared typed-document tables also hold token usage writes, which are not content. */
  private function contentDocumentWrite(QueryExecuted $event): bool
  {
    $sql = $event->sql;
    $owners = ['page_id', 'page_slot_id', 'page_layout_id', 'block_id', 'block_text_translation_id'];
    if (preg_match('/\binsert\b/i', $sql) && str_contains($sql, 'wbcms_persistent_documents')) {
      return (bool) preg_match('/\b('.implode('|', $owners).')\b/', $sql);
    }
    if (! array_is_list($event->bindings)) {
      return true; // Named/raw bindings have no positional ownership guarantee.
    }
    $ids = [];
    $column = str_contains($sql, 'wbcms_persistent_documents') ? 'id' : 'document_id';
    if (preg_match('/\bwhere\s+["`]?'.$column.'["`]?\s*=\s*\?/i', $sql, $match, PREG_OFFSET_CAPTURE)) {
      $index = substr_count(substr($sql, 0, $match[0][1]), '?');
      $ids[] = $event->bindings[$index] ?? null;
    } elseif (preg_match('/^\s*insert\s+into\s+\S+\s*\(([^)]+)\)\s*values\s*\(/i', $sql, $match)) {
      $columns = array_map(fn ($name) => trim($name, " \t\n\r\0\x0B\"`"), explode(',', $match[1]));
      $index = array_search('document_id', $columns, true);
      if ($index !== false && count($event->bindings) % count($columns) === 0) {
        for ($position = $index; $position < count($event->bindings); $position += count($columns)) {
          $ids[] = $event->bindings[$position];
        }
      }
    }
    $ids = array_unique(array_filter($ids, fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id))));
    if ($ids === []) {
      return true; // Unrecognized raw/bulk SQL invalidates conservatively.
    }
    $connection = $event->connection;
    $scope = $this->activeSnapshotScope($connection);
    $ids = array_values(array_filter($ids, fn ($id) => ! isset($scope['known'][$id])));
    if ($ids === []) {
      return false;
    }
    $lock = $scope !== null && array_diff($ids, array_keys($scope['documents'])) === [];
    $projection = $lock ? ['id', ...$owners, 'attribute', 'page_revision_id', 'shared_slot_revision_id'] : $owners;
    $documents = $connection->table('wbcms_persistent_documents')->whereIn('id', $ids)
      ->when($lock, fn ($query) => $query->lockForUpdate())->get($projection);
    if ($documents->count() !== count($ids)) {
      return true;
    }
    $current = $this->activeSnapshotScope($connection);
    $content = false;
    foreach ($documents as $document) {
      $hasContentOwner = false;
      foreach ($owners as $owner) {
        $hasContentOwner = $hasContentOwner || $document->{$owner} !== null;
      }
      $content = $content || $hasContentOwner;
      if (! $hasContentOwner && $lock && $current !== null && $current['generation'] === $scope['generation']
        && isset($current['documents'][$document->id]) && $document->attribute === 'snapshot'
        && ($document->page_revision_id !== null || $document->shared_slot_revision_id !== null)) {
        $current['known'][$document->id] = true;
      }
    }
    if ($current !== null) {
      $this->snapshotScopes[$connection] = $current;
    }

    return $content;
  }

  private function activeSnapshotScope(Connection $connection): ?array
  {
    if (! isset($this->snapshotScopes[$connection]) || $connection->transactionLevel() === 0
      || $connection->getEventDispatcher() !== app('events')) {
      return null;
    }
    $scope = $this->snapshotScopes[$connection];
    if ($scope['pdo'] !== $connection->getPdo() || $scope['database'] !== $connection->getDatabaseName() || $scope['prefix'] !== $connection->getTablePrefix()) {
      $scope['known'] = [];
      $scope['generation']++;
      $scope['pdo'] = $connection->getPdo();
      $scope['database'] = $connection->getDatabaseName();
      $scope['prefix'] = $connection->getTablePrefix();
      $this->snapshotScopes[$connection] = $scope;
    }

    return $scope;
  }

  public function current(): string
  {
    $connection = DB::connection();
    app(ContentVersionUpdates::class)->flush($connection, self::TABLE);

    return hash('sha256', serialize([
      $connection->table(self::TABLE)->useWritePdo()->where('id', 1)->value('generation'),
      WebBlocks::version(), app(PublicMount::class)->prefix(), config('app.url'),
    ]));
  }
}
