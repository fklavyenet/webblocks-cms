<?php

namespace WebBlocks\Cms\Support\Relational;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Throwable;
use WeakMap;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockTextTranslation;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\CmsModel;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Revisions\RestoredBlockReads;
use WebBlocks\Cms\Support\Revisions\RestoredIndexTargets;
use WebBlocks\Cms\Support\Seo\SeoSourceVersion;

class PersistentDocuments
{
  private ?bool $available = null;

  private array $retired = [];

  private ?array $cloneMetadata = null;

  private int $cloneMetadataCount = 0;

  private int $cloneMetadataBytes = 0;

  private int $cloneMetadataNodes = 0;

  private ?Connection $restoreConnection = null;

  private mixed $restorePdo = null;

  private ?string $restoreDatabase = null;

  private string $restorePrefix = '';

  private ?SnapshotInsertLimits $restoreLimits = null;

  private ?WeakMap $restoreHooks = null;

  private bool $flushingClone = false;

  private ?Throwable $restoreFailure = null;

  private int $normalizationReads = 0;

  /** Only internal normalization may read its own encoded, not-yet-flushed new-owner meta. */
  public function duringRestoreNormalization(Closure $read): mixed
  {
    if ($this->restoreConnection === null) {
      return $read();
    }
    $this->assertRestoreConnection();
    $this->normalizationReads++;
    try {
      return $read();
    } finally {
      $this->normalizationReads--;
    }
  }

  private function primePendingNormalizationMeta(Collection $models): bool
  {
    if ($this->normalizationReads === 0 || $this->restoreConnection === null || $this->flushingClone
      || ! $models->first() instanceof BlockTextTranslation
      || BlockTextTranslation::getEventDispatcher() !== app('events')
      || (BlockTextTranslation::getAllGlobalScopes()[BlockTextTranslation::class] ?? []) !== []
      || app('events')->hasListeners('eloquent.retrieved: '.BlockTextTranslation::class)) {
      return false;
    }
    $this->assertRestoreConnection();
    $pending = $this->cloneMetadata['block_text_translation_id'] ?? [];
    foreach ($models as $model) {
      if ($model->getConnection() !== $this->restoreConnection || ! array_key_exists($model->getKey(), $pending)) {
        return false;
      }
    }
    foreach ($models as $model) {
      [$format, , $nodes, $records] = $pending[$model->getKey()];
      $rows = [];
      foreach ($records as $table => $items) {
        foreach ($items as $item) {
          $rows[$table][$item['item_index']] = (object) $item;
        }
      }
      $value = SettingTree::decode(array_map(fn ($node) => (object) $node, $nodes), $rows, null, self::tables());
      $model->seedRelationalValue('meta', $format === 'json' ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : $value);
      $model->markPersistentDocumentsPrimed();
    }

    return true;
  }

  /** Reads and independently rollbackable work must see all prior metadata. */
  public function duringRestoreMetadata(Closure $write): mixed
  {
    $connection = DB::connection();
    if ($this->restoreConnection !== null || $this->cloneMetadata !== null || $connection->transactionLevel() === 0
      || $connection->getEventDispatcher() !== app('events')) {
      return $write();
    }
    $this->restoreConnection = $connection;
    $this->restorePdo = $connection->getPdo();
    $this->restoreDatabase = $connection->getDatabaseName();
    $this->restorePrefix = $connection->getTablePrefix();
    try {
      $this->restoreLimits = SnapshotInsertLimits::forConnection($connection);
      $this->restoreHooks ??= new WeakMap;
      if (! isset($this->restoreHooks[$connection])) {
        // Resolve the current container at execution time, not a request instance.
        $connection->beforeExecuting(static function ($sql, $bindings, $connection): void {
          if (app()->resolved(self::class)) {
            app(self::class)->beforeRestoreQuery($connection, $sql);
          }
        });
        $connection->beforeStartingTransaction(static function ($connection): void {
          if (app()->resolved(self::class)) {
            app(self::class)->flushRestoreMetadata($connection);
          }
        });
        $this->restoreHooks[$connection] = true;
      }

      $result = null;
      $this->duringBlockClone(function () use ($write, &$result): void {
        $result = $write();
      });
      if ($this->restoreFailure !== null) {
        throw $this->restoreFailure;
      }

      return $result;
    } finally {
      $this->restoreConnection = null;
      $this->restorePdo = null;
      $this->restoreDatabase = null;
      $this->restorePrefix = '';
      $this->restoreLimits = null;
      $this->restoreFailure = null;
    }
  }

  /** The existing creation helper still dispatches every ordinary model event. */
  public function createRestored(CmsModel $model): bool
  {
    if ($this->restoreConnection === null || ! ($model instanceof Block || $model instanceof BlockTextTranslation)) {
      return $model->save();
    }
    $this->assertRestoreConnection();
    if ($model->getConnection() !== $this->restoreConnection) {
      return $model->save();
    }

    return $model instanceof Block
      ? app(RestoredIndexTargets::class)->creating($model, fn () => $model->createInTransaction())
      : $model->createInTransaction();
  }

  public function beforeRestoreQuery(Connection $connection, string $sql): void
  {
    if ($this->restoreConnection !== $connection || $this->flushingClone || empty($this->cloneMetadata)) {
      return;
    }
    $prefix = preg_quote($connection->getTablePrefix().'wbcms_', '/');
    $ownerWrite = preg_match('/^\s*(insert|update|delete|replace)\b/i', $sql)
      && preg_match('/\b'.$prefix.'(?:blocks|block_text_translations|pages|sites|locales)\b/i', $sql)
      && (! preg_match('/^\s*insert\s+into\b/i', $sql) || preg_match('/\b(conflict|duplicate|replace)\b/i', $sql));
    if (preg_match('/\b'.$prefix.'(?:persistent_documents|persistent_nodes|persistent_items|persistent_metadata|import_identity_maps|import_copied_files)\b/i', $sql)
      || $ownerWrite
      || ! preg_match('/^\s*(select|insert|update|delete|pragma|explain|show|describe)\b/i', $sql)) {
      $this->flushBlockClone();
    }
  }

  public function flushRestoreMetadata(Connection $connection): void
  {
    if ($this->restoreConnection === $connection && ! $this->flushingClone && ! empty($this->cloneMetadata)) {
      $this->flushBlockClone();
    }
  }

  public function rollbackRestoreMetadata(Connection $connection): void
  {
    if ($this->restoreConnection === $connection && $this->cloneMetadata !== null) {
      // Pre-savepoint values were flushed before beginTransaction. Discard only
      // the new, rolled-back owners that could have accumulated afterwards.
      $this->cloneMetadata = [];
      $this->cloneMetadataCount = $this->cloneMetadataBytes = $this->cloneMetadataNodes = 0;
    }
  }

  private function assertRestoreConnection(): void
  {
    if ($this->restoreFailure !== null) {
      throw $this->restoreFailure;
    }
    $connection = $this->restoreConnection;
    if ($connection === null || DB::connection() !== $connection || $connection->transactionLevel() === 0
      || $connection->getEventDispatcher() !== app('events') || $connection->getPdo() !== $this->restorePdo
      || $connection->getDatabaseName() !== $this->restoreDatabase || $connection->getTablePrefix() !== $this->restorePrefix) {
      throw new LogicException('The metadata restoration transaction changed connection identity.');
    }
  }

  /** Buffer fresh clone metadata within the caller's content transaction. */
  public function duringBlockClone(Closure $copy): void
  {
    if ($this->cloneMetadata !== null) {
      $copy();

      return;
    }
    $this->cloneMetadata = [];
    $this->cloneMetadataCount = $this->cloneMetadataBytes = $this->cloneMetadataNodes = 0;
    try {
      $copy();
      $this->flushBlockClone();
    } finally {
      $this->cloneMetadata = null;
      $this->cloneMetadataCount = $this->cloneMetadataBytes = $this->cloneMetadataNodes = 0;
      $this->cloneMetadataCount = $this->cloneMetadataBytes = $this->cloneMetadataNodes = 0;
    }
  }

  public function available(): bool
  {
    return app()->bound('db.schema') && ($this->available ??= Schema::hasTable('wbcms_persistent_documents'));
  }

  public function clear(): void
  {
    $this->available = null;
    $this->retired = [];
  }

  public function retired(CmsModel $model, string $attribute): bool
  {
    if (! $this->available() || ! in_array($attribute, [...PersistentFields::attributes($model), ...SettingDefinitions::attributes($model)], true)) {
      return false;
    }
    $key = $model->getTable().'.'.$attribute;

    return $this->retired[$key] ??= ! Schema::hasColumn($model->getTable(), $attribute);
  }

  public static function tables(): array
  {
    return SnapshotFields::tables() + [
      'persistent_items' => ['value' => 'variant'],
      'persistent_metadata' => array_fill_keys(explode(' ', 'status type version source product channel checksum_sha256 created_at finished_at summary output archive_disk archive_path archive_name site_handle site_domain mode'), 'text')
        + array_fill_keys(explode(' ', 'site_id pages blocks media assets locales translations duration_ms warning_count schema_version'), 'integer'),
      'import_identity_maps' => ['value' => 'variant'],
      'import_copied_files' => ['value' => 'variant'],
    ];
  }

  private function selector(string $attribute): callable
  {
    return function (array $path, array $positions, array $ancestors, int $index) use ($attribute): ?array {
      if ($attribute === 'snapshot') {
        return SnapshotFields::binding($path, $positions, $ancestors, $index);
      }
      if ($attribute === 'resume_state' && count($path) === 3 && $path[0] === 'maps') {
        return ['import_identity_maps', $index, 'value', 'variant', (string) $path[1].':'.(string) $path[2]];
      }
      if ($attribute === 'resume_state' && count($path) === 2 && $path[0] === 'copied_files') {
        return ['import_copied_files', $index, 'value', 'variant', (string) $path[1]];
      }
      if (count($path) === 1 && is_int($path[0])) {
        return ['persistent_items', $index, 'value', 'variant', (string) $path[0]];
      }
      $type = count($path) === 1 ? (self::tables()['persistent_metadata'][$path[0]] ?? null) : null;

      return $type === null ? null : ['persistent_metadata', 0, $path[0], $type, null];
    };
  }

  public function read(CmsModel $model, string $attribute): array
  {
    if (! $model->exists || ! $this->available() || PersistentFields::definition($model) === null) {
      return [false, null];
    }
    $owner = PersistentFields::definition($model)[0];
    if (array_key_exists($model->getKey(), $this->cloneMetadata[$owner] ?? [])) {
      $this->flushBlockClone($owner);
    }
    $documents = DB::table('wbcms_persistent_documents')->where($owner, $model->getKey())->where('attribute', $attribute)->get();
    if ($documents->isEmpty()) {
      return [false, null];
    }

    return [true, $this->values($documents)[$documents->first()->id]];
  }

  public function prime(Collection $models): void
  {
    $models = $models->filter(fn (CmsModel $model) => $model->getKey() !== null && ! $model->persistentDocumentsPrimed() && (! in_array(class_basename($model), ['PageRevision', 'SharedSlotRevision'], true) || array_key_exists('site_id', $model->getAttributes())));
    if ($models->isEmpty() || ! $this->available()) {
      return;
    }
    $definition = PersistentFields::definition($models->first());
    if ($definition === null) {
      return;
    }
    $owner = $definition[0];
    foreach ($models->chunk($models->first() instanceof BlockTextTranslation ? 200 : 100) as $chunk) {
      if ($this->primePendingNormalizationMeta($chunk)) {
        continue;
      }
      if (isset($this->cloneMetadata[$owner]) && array_intersect_key($this->cloneMetadata[$owner], array_flip($chunk->modelKeys())) !== []) {
        $this->flushBlockClone($owner);
      }
      $pages = $chunk->first() instanceof Page;
      $restoreReads = ($pages || $chunk->first() instanceof Block) && app()->resolved(RestoredBlockReads::class) ? app(RestoredBlockReads::class) : null;
      $indexTargets = $pages && app()->resolved(RestoredIndexTargets::class) ? app(RestoredIndexTargets::class) : null;
      $restoreReads?->beforeDocumentRead(DB::connection(), $chunk->modelKeys(), $pages);
      $indexTargets?->beforeDocuments(DB::connection(), $chunk->modelKeys());
      $documents = DB::table('wbcms_persistent_documents')->whereIn($owner, $chunk->modelKeys())->get();
      $restoreReads?->rememberDocuments(DB::connection(), $chunk->modelKeys(), $documents, $pages);
      $indexTargets?->rememberDocuments(DB::connection(), $chunk->modelKeys(), $documents);
      $owners = $chunk->keyBy(fn ($model) => (string) $model->getKey());
      $compositions = [];
      $compositionItems = collect();
      foreach (array_keys($definition[2]) as $attribute) {
        $mapped = DocumentFields::for($chunk->first(), $attribute);
        if ($mapped !== null && app(RelationalDocuments::class)->available($mapped)) {
          $compositions[$attribute] = DB::table($mapped['table'])->whereIn($mapped['owner'], $chunk->modelKeys())->get()->keyBy($mapped['owner']);
          if ($mapped['lists'] !== []) {
            $compositionItems = DB::table('wbcms_page_runtime_items')->whereIn('page_id', $chunk->modelKeys())->get()->sortBy('position')->groupBy('page_id');
          }
        }
      }
      $pending = collect();
      $bytes = $count = 0;
      $flush = function () use (&$pending, &$bytes, &$count, $owners, $owner, $compositions, $compositionItems): void {
        $values = $this->values($pending);
        foreach ($pending as $document) {
          $model = $owners[(string) $document->{$owner}];
          $value = $values[$document->id];
          $definition = DocumentFields::for($model, $document->attribute);
          if ($definition !== null) {
            $value = app(RelationalDocuments::class)->read($model, $definition, $value, ($compositions[$document->attribute] ?? collect())->get($model->getKey()), $compositionItems->get($model->getKey(), collect()));
          } elseif ($model instanceof CmsApiToken) {
            $value = app(TokenRelations::class)->read($model, $document->attribute, $value);
          }
          $model->seedRelationalValue($document->attribute, $value);
        }
        $pending = collect();
        $bytes = $count = 0;
      };
      foreach ($documents as $document) {
        if ($pending->isNotEmpty() && ($bytes + (int) $document->payload_bytes > 2097152 || $count + (int) $document->node_count > 5000)) {
          $flush();
        }
        $pending->push($document);
        $bytes += (int) $document->payload_bytes;
        $count += (int) $document->node_count;
      }
      if ($pending->isNotEmpty()) {
        $flush();
      }
      foreach ($chunk as $model) {
        $model->markPersistentDocumentsPrimed();
      }
    }
  }

  /** Restrict no-op detection to content metadata, never revision snapshots. */
  public function unchangedMeta(CmsModel $model, mixed $value): bool
  {
    if (! ($model instanceof Block || $model instanceof BlockTextTranslation)) {
      return false;
    }
    $definition = PersistentFields::definition($model);
    if ($definition === null || ! isset($definition[2]['meta'])) {
      return false;
    }
    $owner = $definition[0];
    if (array_key_exists($model->getKey(), $this->cloneMetadata[$owner] ?? [])) {
      $this->flushBlockClone($owner);
    }
    $documents = DB::table('wbcms_persistent_documents')->where($owner, $model->getKey())
      ->where('attribute', 'meta')->lockForUpdate()->get();
    if ($documents->isEmpty()) {
      return false;
    }
    [$desired, $format] = $this->prepareValue($value, $definition[2]['meta']);
    $document = $documents->first();
    if ($document->format !== $format) {
      return false;
    }
    $current = $this->values($documents, lock: true)[$document->id];
    if ($format === 'json') {
      $current = json_decode($current, flags: JSON_THROW_ON_ERROR);
    }

    return SettingTree::sameValue($current, $desired);
  }

  private function values(Collection $documents, bool $lock = false): array
  {
    if ($documents->isEmpty()) {
      return [];
    }
    $ids = $documents->pluck('id');
    $nodes = DB::table('wbcms_persistent_nodes')->whereIn('document_id', $ids)->when($lock, fn ($query) => $query->lockForUpdate())->get()->groupBy('document_id')->map(fn ($group) => $group->sortBy('node_index')->values());
    $used = $nodes->flatten(1)->where('kind', 'column')->pluck('ref_table')->unique();
    $rows = [];
    foreach ($used as $table) {
      if (! isset(self::tables()[$table])) {
        throw new \RuntimeException('Relational document references an invalid record type.');
      }
      $rows[$table] = DB::table('wbcms_'.$table)->whereIn('document_id', $ids)->when($lock, fn ($query) => $query->lockForUpdate())->get()->groupBy('document_id');
    }
    $values = [];
    foreach ($documents as $document) {
      $records = [];
      foreach ($rows as $table => $groups) {
        $records[$table] = $groups->get($document->id, collect())->keyBy('item_index')->all();
      }
      $value = SettingTree::decode($nodes->get($document->id, collect())->all(), $records, null, self::tables());
      $values[$document->id] = $document->format === 'json' ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : $value;
    }

    return $values;
  }

  public function write(CmsModel $model, string $attribute, mixed $value, bool $newOwner = false): void
  {
    $definition = PersistentFields::definition($model);
    if ($definition === null || ! isset($definition[2][$attribute])) {
      throw new \InvalidArgumentException('Unknown persistent attribute.');
    }
    $owner = $definition[0];
    if (array_key_exists($model->getKey(), $this->cloneMetadata[$owner] ?? [])) {
      $this->flushBlockClone($owner);
    }
    [$value, $format, $bytes] = $this->prepareValue($value, $definition[2][$attribute]);
    if ($newOwner && $this->cloneMetadata !== null && $attribute === 'meta' && in_array($owner, ['block_id', 'block_text_translation_id'], true)) {
      [$nodes, $records] = SettingTree::encode($value, false, $attribute, $this->selector($attribute));
      $this->cloneMetadata[$owner][$model->getKey()] = [$format, $bytes, $nodes, $records];
      $this->cloneMetadataBytes += $bytes;
      $this->cloneMetadataNodes += count($nodes);
      if (++$this->cloneMetadataCount >= 300 || $this->cloneMetadataBytes >= 2097152 || $this->cloneMetadataNodes >= 5000) {
        $this->flushBlockClone();
      } elseif (count($this->cloneMetadata[$owner]) >= 150) {
        $this->flushBlockClone($owner);
      }

      return;
    }
    $snapshot = $attribute === 'snapshot';
    if (! $snapshot) {
      [$nodes, $records] = SettingTree::encode($value, false, $attribute, $this->selector($attribute));
    }
    $identity = [$definition[0] => $model->getKey(), 'attribute' => $attribute];
    $existing = DB::table('wbcms_persistent_documents')->where($identity)->lockForUpdate()->first(['id']);
    $metadata = ['format' => $format, 'payload_bytes' => $bytes, 'node_count' => $snapshot ? 0 : count($nodes)];
    if ($existing === null) {
      // A new document has no prior child rows to remove.
      $id = (int) DB::table('wbcms_persistent_documents')->insertGetId($identity + $metadata);
    } else {
      $id = (int) $existing->id;
      DB::table('wbcms_persistent_documents')->where('id', $id)->update($metadata);
      DB::table('wbcms_persistent_nodes')->where('document_id', $id)->delete();
      $tables = $snapshot ? array_keys(SnapshotFields::tables()) : ['persistent_items', 'persistent_metadata', 'import_identity_maps', 'import_copied_files'];
      foreach ($tables as $table) {
        DB::table('wbcms_'.$table)->where('document_id', $id)->delete();
      }
    }
    if ($snapshot) {
      app(SeoSourceVersion::class)->duringSnapshotDocument(DB::connection(), $id, function () use ($value, $attribute, $id): void {
        // Keep only a batch of the structural rows in memory. Typed records are
        // written below, within the same model or migration transaction.
        $snapshotLimits = SnapshotInsertLimits::forConnection(DB::connection());
        $batch = null;
        $writeNode = function (array $node) use ($id, &$batch, $snapshotLimits): void {
          $row = ['document_id' => $id, ...$node];
          $batch ??= new SnapshotInsertBatch(DB::connection(), 'wbcms_persistent_nodes', array_fill_keys(array_keys($row), null), $snapshotLimits);
          $batch->add($row);
        };
        [$nodes, $records, $nodeCount] = SettingTree::encode($value, false, $attribute, $this->selector($attribute), true, $writeNode);
        $batch?->finish();
        DB::table('wbcms_persistent_documents')->where('id', $id)->update(['node_count' => $nodeCount]);
        foreach ($records as $table => $items) {
          $this->insertRecords($table, $items, $id, $snapshotLimits);
        }
      });

      return;
    }
    foreach ($records as $table => $items) {
      $this->insertRecords($table, $items, $id);
    }
    foreach (array_chunk($nodes, 300) as $chunk) {
      DB::table('wbcms_persistent_nodes')->insert(array_map(fn ($node) => ['document_id' => $id, ...$node], $chunk));
    }
  }

  private function prepareValue(mixed $value, string $type): array
  {
    $bytes = is_string($value) ? strlen($value) : strlen(json_encode($value, JSON_THROW_ON_ERROR));
    if ($type === 'text' && is_string($value)) {
      $decoded = json_decode($value);
      if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || $decoded instanceof \stdClass)) {
        return [$decoded, 'json', $bytes];
      }
    }

    return [$value, 'value', $bytes];
  }

  private function flushBlockClone(?string $onlyOwner = null): void
  {
    if (empty($this->cloneMetadata)) {
      return;
    }
    if ($this->restoreConnection !== null) {
      $this->assertRestoreConnection();
    }
    $flushing = $this->flushingClone;
    $this->flushingClone = true;
    try {
      $this->writeCloneGroups($onlyOwner);
    } catch (Throwable $exception) {
      if ($this->restoreConnection !== null) {
        $this->restoreFailure ??= $exception;
      }

      throw $exception;
    } finally {
      $this->flushingClone = $flushing;
    }
  }

  private function writeCloneGroups(?string $onlyOwner): void
  {
    $groups = $onlyOwner === null ? $this->cloneMetadata : array_intersect_key($this->cloneMetadata, [$onlyOwner => true]);
    foreach ($groups as $owner => $values) {
      unset($this->cloneMetadata[$owner]);
      $this->cloneMetadataCount -= count($values);
      foreach ($values as [, $bytes, $nodes]) {
        $this->cloneMetadataBytes -= $bytes;
        $this->cloneMetadataNodes -= count($nodes);
      }
    }
    foreach ($groups as $owner => $values) {
      $documents = [];
      foreach ($values as $key => [$format, $bytes, $nodes]) {
        $documents[] = [$owner => $key, 'attribute' => 'meta', 'format' => $format, 'payload_bytes' => $bytes, 'node_count' => count($nodes)];
      }
      $existing = DB::table('wbcms_persistent_documents')->whereIn($owner, array_keys($values))->where('attribute', 'meta')->lockForUpdate()->pluck('id');
      if ($existing->isEmpty()) {
        $this->insertCloneRows('wbcms_persistent_documents', $documents);
      } else {
        // A model observer may have written metadata during creation.
        // Existing headers came from an observer. Use single-row replacement
        // for that fallback, keeping packets and bindings as small as before.
        $size = $this->restoreLimits === null ? count($documents) : 1;
        foreach (array_chunk($documents, $size) as $batch) {
          DB::table('wbcms_persistent_documents')->upsert($batch, [$owner, 'attribute'], ['format', 'payload_bytes', 'node_count']);
        }
        DB::table('wbcms_persistent_nodes')->whereIn('document_id', $existing)->delete();
        foreach (['persistent_items', 'persistent_metadata', 'import_identity_maps', 'import_copied_files'] as $table) {
          DB::table('wbcms_'.$table)->whereIn('document_id', $existing)->delete();
        }
      }
      // Resolve IDs by owner identity so concurrent inserts and auto-increment
      // gaps cannot associate a payload with the wrong document.
      $ids = DB::table('wbcms_persistent_documents')->whereIn($owner, array_keys($values))->where('attribute', 'meta')->pluck('id', $owner);
      $nodeRows = $recordRows = [];
      foreach ($values as $key => [, , $nodes, $records]) {
        $id = $ids[$key];
        foreach ($nodes as $node) {
          $nodeRows[] = ['document_id' => $id, ...$node];
        }
        foreach ($records as $table => $items) {
          foreach ($items as $item) {
            $recordRows[$table][] = ['document_id' => $id, ...$item];
          }
        }
      }
      foreach (array_chunk($nodeRows, 300) as $batch) {
        $this->insertCloneRows('wbcms_persistent_nodes', $batch);
      }
      foreach ($recordRows as $table => $items) {
        $this->insertRecords($table, $items, snapshotLimits: $this->restoreLimits);
      }
    }
  }

  private function insertCloneRows(string $table, array $rows): void
  {
    if ($this->restoreLimits === null) {
      DB::table($table)->insert($rows);

      return;
    }
    $batch = new SnapshotInsertBatch(DB::connection(), $table, array_fill_keys(array_keys($rows[0]), null), $this->restoreLimits);
    foreach ($rows as $row) {
      $batch->add($row);
    }
    $batch->finish();
  }

  private function insertRecords(string $table, array $items, ?int $documentId = null, ?SnapshotInsertLimits $snapshotLimits = null): void
  {
    $columns = $documentId === null ? [] : ['document_id' => $documentId];
    foreach ($items as $item) {
      $columns += array_fill_keys(array_keys($item), null);
    }
    if ($snapshotLimits !== null) {
      $batch = new SnapshotInsertBatch(DB::connection(), 'wbcms_'.$table, $columns, $snapshotLimits);
      foreach ($items as $item) {
        $batch->add($item);
      }
      $batch->finish();

      return;
    }
    $batchSize = max(1, min(100, intdiv(900, count($columns))));
    foreach (array_chunk($items, $batchSize) as $batch) {
      DB::table('wbcms_'.$table)->insert(array_map(fn ($item) => array_replace($columns, $item), $batch));
    }
  }
}
