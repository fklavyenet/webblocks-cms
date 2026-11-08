<?php

namespace WebBlocks\Cms\Support\Revisions;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockButtonTranslation;
use WebBlocks\Cms\Models\BlockContactFormTranslation;
use WebBlocks\Cms\Models\BlockGalleryItemTranslation;
use WebBlocks\Cms\Models\BlockImageTranslation;
use WebBlocks\Cms\Models\BlockPluginTranslation;
use WebBlocks\Cms\Models\BlockTextTranslation;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\CmsModel;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Support\Blocks\BlockCopyCatalog;
use WebBlocks\Cms\Support\Relational\PersistentDocuments;
use WebBlocks\Cms\Support\Relational\StructuredSettings;

/** Reuse one restored block only while its SQL values remain current. */
final class RestoredBlockReads
{
  private const RELATIONS = ['textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations'];

  private array $contexts = [];

  private array $batches = [];

  private int $writeGeneration = 0;

  /** Keep each block's writes and normalization together, with bounded read-ahead. */
  public function each(Collection $snapshots, array $idMap, Closure $write): void
  {
    $pending = [];
    $bytes = 0;
    foreach ($snapshots as $snapshot) {
      $size = strlen(serialize($snapshot));
      if ($pending !== [] && (count($pending) >= 50 || $bytes + $size > 1048576)) {
        $this->batch($pending, $idMap, $write);
        $pending = [];
        $bytes = 0;
      }
      $pending[] = $snapshot;
      $bytes += $size;
    }
    if ($pending !== []) {
      $this->batch($pending, $idMap, $write);
    }
  }

  private function batch(array $snapshots, array $idMap, Closure $write): void
  {
    $connection = DB::connection();
    $preload = $connection->transactionLevel() > 0 && $connection->getEventDispatcher() === app('events') && $this->canReuse()
      && in_array($connection->getDriverName(), ['sqlite', 'mysql', 'mariadb'], true)
      && app(PersistentDocuments::class)->available() && app(StructuredSettings::class)->available()
      && strlen(serialize($snapshots)) <= 1048576;
    if ($preload) {
      // Actual SQL document sizes may differ from the snapshot after creation observers.
      app(PersistentDocuments::class)->flushRestoreMetadata($connection);
      $ids = array_map(fn ($snapshot) => $idMap[$snapshot['snapshot_id']], $snapshots);
      $generation = $this->writeGeneration;
      $withinBudget = $this->withinReadBudget($ids);
      if ($generation !== $this->writeGeneration) {
        $preload = false;
      } elseif (! $withinBudget) {
        if (count($snapshots) > 1) {
          foreach (array_chunk($snapshots, (int) ceil(count($snapshots) / 2)) as $chunk) {
            $this->batch($chunk, $idMap, $write);
          }

          return;
        }
        $preload = false; // One oversized owner keeps the original single-block read path.
      }
    }
    $batch = new RestoredReadBatch($connection, count($this->contexts));
    $this->batches[] = $batch;
    try {
      if ($preload && $this->validBatch($batch)) {
        app(PersistentDocuments::class)->flushRestoreMetadata($connection);
        $epoch = $batch->epoch;
        $models = Block::query()->whereKey(array_map(fn ($snapshot) => $idMap[$snapshot['snapshot_id']], $snapshots))->get();
        if ($epoch === $batch->epoch && $this->validBatch($batch)) {
          $models->load([...self::RELATIONS, 'pluginTranslations']);
        }
        if ($epoch === $batch->epoch && $this->validBatch($batch)) {
          foreach ($models as $model) {
            if (! $this->containsObject($model->settings) && ! $this->containsObject($model->meta)) {
              $batch->models[$model->id] = (clone $model)->unsetRelations();
            }
            $batch->relations[$model->id] = $model->getRelations();
          }
        }
        unset($models, $model);
      }
      foreach ($snapshots as $snapshot) {
        $id = $idMap[$snapshot['snapshot_id']];
        $this->duringBlock($id, fn (Block $block) => $write($block, $snapshot));
        unset($batch->models[$id], $batch->relations[$id]);
      }
    } finally {
      array_pop($this->batches);
    }
  }

  private function withinReadBudget(array $ids): bool
  {
    $bytes = $rows = $nodes = 0;
    foreach ([
      DB::table('wbcms_persistent_documents')->whereIn('block_id', $ids),
      DB::table('wbcms_setting_documents')->whereIn('block_id', $ids),
      DB::table('wbcms_persistent_documents')->whereIn('block_text_translation_id', DB::table('wbcms_block_text_translations')->whereIn('block_id', $ids)->select('id')),
    ] as $query) {
      $size = $query->selectRaw('count(*) as budget_rows, coalesce(sum(payload_bytes), 0) as budget_bytes, coalesce(sum(node_count), 0) as budget_nodes')->first();
      $rows += (int) $size->budget_rows;
      $bytes += (int) $size->budget_bytes;
      $nodes += (int) $size->budget_nodes;
      if ($rows > 1000 || $bytes > 1048576 || $nodes > 5000) {
        return false;
      }
    }
    $grammar = DB::connection()->getQueryGrammar();
    foreach ([
      'blocks' => ['type', 'source_type', 'slot', 'title', 'subtitle', 'content', 'url', 'variant', 'status'],
      'block_text_translations' => ['title', 'eyebrow', 'subtitle', 'content'],
      'block_button_translations' => ['title'],
      'block_image_translations' => ['caption', 'alt_text'],
      'block_contact_form_translations' => ['title', 'content', 'submit_label', 'success_message', 'consent_label'],
      'block_plugin_translations' => ['field', 'value'],
    ] as $table => $fields) {
      $lengths = array_map(function ($field) use ($grammar): string {
        $value = 'coalesce('.$grammar->wrap($field).", '')";

        return DB::connection()->getDriverName() === 'sqlite' ? 'length(cast('.$value.' as blob))' : 'length('.$value.')';
      }, $fields);
      $size = DB::table('wbcms_'.$table)->whereIn($table === 'blocks' ? 'id' : 'block_id', $ids)
        ->selectRaw('count(*) as budget_rows, coalesce(sum('.implode(' + ', $lengths).'), 0) as budget_bytes')->first();
      $rows += (int) $size->budget_rows;
      $bytes += (int) $size->budget_bytes;
      if ($rows > 1000 || $bytes > 1048576) {
        return false;
      }
    }

    return true;
  }

  private function validBatch(RestoredReadBatch $batch): bool
  {
    $connection = DB::connection();

    return $connection === $batch->connection && $connection->transactionLevel() > 0
      && $connection->getEventDispatcher() === app('events') && $connection->getPdo() === $batch->pdo
      && $connection->getDatabaseName() === $batch->database && $connection->getTablePrefix() === $batch->prefix && $this->canReuse();
  }

  private function currentBatch(): ?RestoredReadBatch
  {
    $batch = $this->batches[array_key_last($this->batches)] ?? null;

    return $batch !== null && count($this->contexts) === $batch->depth + 1 && $this->validBatch($batch) ? $batch : null;
  }

  public function duringBlock(int $id, Closure $write): mixed
  {
    $connection = DB::connection();
    if ($connection->transactionLevel() === 0 || $connection->getEventDispatcher() !== app('events') || ! $this->canReuse()) {
      return $write(Block::query()->findOrFail($id));
    }
    $state = new RestoredBlockReadState($id, $connection);
    $this->contexts[] = $state;
    try {
      $epoch = $state->fields;
      $cached = $this->currentBatch()?->models[$id] ?? null;
      $state->model = $cached !== null ? clone $cached : Block::query()->findOrFail($id);
      if ($cached !== null) {
        $state->identitiesKnown = true;
        $state->documentIds = $this->currentBatch()->documentIds;
      }
      $state->fieldStamp = $epoch === $state->fields ? $epoch : -1;

      return $write($state->model);
    } finally {
      array_pop($this->contexts);
    }
  }

  public function forNormalization(Block $block, bool $legacyFallback): ?Block
  {
    $state = $this->current($block);
    if ($state === null) {
      app(PersistentDocuments::class)->flushRestoreMetadata($block->getConnection());

      return $this->nativeTarget($block, $legacyFallback);
    }
    $state->trusted = false;
    $target = $state->model ?? $block;
    if ($state->fieldStamp !== $state->fields) {
      $epoch = $state->fields;
      $target = $block->fresh();
      if ($target === null) {
        throw (new ModelNotFoundException)->setModel(Block::class, [$block->id]);
      }
      $state->fieldStamp = $epoch === $state->fields ? $epoch : -1;
    }
    $epoch = $state->writes;
    app(PersistentDocuments::class)->duringRestoreNormalization(fn () => $this->loadRelations($target, self::RELATIONS));
    $hasRows = $legacyFallback;
    foreach (self::RELATIONS as $relation) {
      $hasRows = $hasRows || $target->getRelation($relation)->isNotEmpty();
    }
    $hasRows = $hasRows || $target->galleryItemTranslations()->exists();
    if ($state->writes !== $epoch || $state->fieldStamp !== $state->fields || ! $this->canReuse()) {
      return $this->nativeTarget($block, $legacyFallback);
    }
    if (! $hasRows) {
      return null;
    }
    $this->loadRelations($target, ['pluginTranslations']);
    if ($target->block_type_id) {
      $target->setRelation('blockType', app(BlockCopyCatalog::class)->find(BlockType::class, $target->block_type_id));
    }
    $canonical = [
      'title' => $target->getRawOriginal('title'),
      'subtitle' => $target->getRawOriginal('subtitle'),
      'content' => $target->getRawOriginal('content'),
      'meta' => $target->getAttribute('meta'),
      'settings' => $target->getAttribute('settings'),
    ];
    if ($state->writes !== $epoch || $state->fieldStamp !== $state->fields || ! $this->canReuse()) {
      return $this->nativeTarget($block, $legacyFallback);
    }
    $state->model = $target;
    $state->canonical = $canonical;
    $state->relationStamp = $epoch;
    $state->trusted = true;

    return $target;
  }

  private function loadRelations(Block $block, array $relations): void
  {
    $batch = $this->currentBatch();
    $missing = [];
    foreach ($relations as $relation) {
      $cached = $batch?->relations[$block->id][$relation] ?? null;
      if ($cached === null || $cached->contains(fn ($row) => $this->containsObject($row->getAttribute('meta')))) {
        $missing[] = $relation;
      } else {
        $block->setRelation($relation, $cached->map(fn ($row) => (clone $row)->unsetRelations()));
      }
    }
    if ($missing !== []) {
      $block->load($missing);
    }
  }

  /** Only skip the already-quiet canonical save, never translation validation. */
  public function canSkipCanonicalSave(Block $block, array $updates): bool
  {
    $state = $this->current($block);
    if ($state === null || ! $state->trusted || $state->canonicalFallback || $state->model !== $block || ! $block->exists || $block->isDirty()
      || $state->relationStamp !== $state->writes || $state->fieldStamp !== $state->fields || $updates === []) {
      return false;
    }
    foreach (['settings', 'meta'] as $attribute) {
      if ($state->canonical[$attribute] !== null && ! is_string($state->canonical[$attribute])) {
        return false; // Keep the model writer's historical value-to-JSON conversion.
      }
    }
    $settings = $state->canonical['settings'];
    $settings = is_string($settings) ? json_decode($settings, true) : $settings;
    if (is_array($settings) && array_key_exists('application_handle', $settings)) {
      return false; // The normal writer also refreshes the resolved SQL reference.
    }
    foreach ($updates as $field => $value) {
      if (! array_key_exists($field, $state->canonical) || ($field !== 'settings' && $value !== null)
        || $state->canonical[$field] !== $value || $block->getAttribute($field) !== $value) {
        return false;
      }
    }

    // Reading a virtual attribute can itself cause SQL or observer writes.
    return $state->trusted && $state->relationStamp === $state->writes && $state->fieldStamp === $state->fields
      && ! $block->isDirty() && $this->current($block) !== null;
  }

  /** Keep the normal saving validation and saved hooks; only seed their inverse. */
  public function primeTranslation(CmsModel $translation): void
  {
    $state = $this->contexts[array_key_last($this->contexts)] ?? null;
    if ($state === null || $state->model === null || $translation->relationLoaded('block')
      || (int) $translation->block_id !== $state->id || $translation->getConnection() !== $state->connection
      || $this->current($state->model) === null) {
      return;
    }
    $target = $state->model;
    if ($state->fieldStamp !== $state->fields) {
      $epoch = $state->fields;
      $target = $target->fresh();
      if ($target === null || $epoch !== $state->fields) {
        return;
      }
      $state->model = $target;
      $state->fieldStamp = $epoch;
    }
    if ($this->containsObject($target->settings) || $this->containsObject($target->meta) || $state->fieldStamp !== $state->fields) {
      return;
    }
    if ($state->pageId !== (int) $target->page_id) {
      $state->pageId = (int) $target->page_id;
      $state->page = null;
      $state->pageIdentitiesKnown = false;
      $state->pageDocumentIds = [];
    }
    if ($state->page === null || $state->pageStamp !== $state->pageWrites) {
      $epoch = $state->pageWrites;
      $fields = $state->fields;
      $page = app(RestoredIndexTargets::class)->pageForTranslation($target) ?? $target->page()->first();
      if ($page === null || $epoch !== $state->pageWrites || $fields !== $state->fields || ! $this->canReuse()) {
        return;
      }
      $state->page = $page;
      $state->pageStamp = $epoch;
    }
    if ($this->containsObject($state->page->settings) || $state->pageStamp !== $state->pageWrites
      || $state->fieldStamp !== $state->fields || $this->current($target) === null) {
      return; // Historical mutable objects must keep independent SQL hydration.
    }
    $page = clone $state->page;
    $page->setRelation('translations', $state->page->translations->map(fn ($row) => (clone $row)->unsetRelations()));
    $translation->setRelation('block', (clone $target)->unsetRelations()->setRelation('page', $page));
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

  public function defaultTranslationExists(Block $block, string $relation, int $localeId): ?bool
  {
    $state = $this->current($block);
    if ($state === null || ! $state->trusted || $state->model !== $block || $state->relationStamp !== $state->writes
      || $state->fieldStamp !== $state->fields || ! $block->relationLoaded($relation)) {
      return null;
    }

    return $block->getRelation($relation)->contains('locale_id', $localeId);
  }

  public function onQuery(QueryExecuted $event): void
  {
    if (str_contains($event->sql, ';') || ! preg_match('/^\s*(select|pragma|explain|show|describe)\b/i', $event->sql)) {
      $this->writeGeneration++;
    }
    foreach ($this->batches as $batch) {
      $batch->onQuery($event, fn ($column) => $this->documentIds($event, $column));
    }
    if ($this->contexts === [] || preg_match('/^\s*(select|pragma|explain|show|describe)\b/i', $event->sql)) {
      return;
    }
    $sql = $event->sql;
    $prefix = preg_quote($event->connection->getTablePrefix().'wbcms_', '/');
    $target = '/^\s*(?:insert\s+into|update|delete\s+from)\s+["`]?'.$prefix;
    $simple = ! str_contains($sql, ';');
    $childWrite = $simple && preg_match($target.'block_(?:text_translations|button_translations|image_translations|contact_form_translations|plugin_translations|gallery_item_translations|media)\b/i', $sql);
    $documentWrite = $simple && preg_match($target.'persistent_documents\b/i', $sql);
    $documentRows = $simple && preg_match($target.'(?:persistent_nodes|persistent_metadata|persistent_items|import_identity_maps|import_copied_files)\b/i', $sql);
    foreach ($this->contexts as $state) {
      $state->writes++;
      $state->trusted = false;
      if ($childWrite) {
        continue;
      }
      $pageUnchanged = false;
      if ($documentWrite) {
        $pageUnchanged = (bool) (preg_match('/^\s*insert\s+into\s+\S+\s*\([^)]+\)\s*values\s*\(/i', $sql) && ! preg_match('/\b(?:page_id|id)\b/i', $sql));
        $ids = $this->documentIds($event, 'id');
        $pageUnchanged = $pageUnchanged || ($state->pageIdentitiesKnown && ! preg_match('/\bpage_id\b/i', $sql) && $ids !== null && array_intersect($state->pageDocumentIds, $ids) === []);
      } elseif ($documentRows) {
        $ids = $this->documentIds($event, 'document_id');
        $pageUnchanged = $state->pageIdentitiesKnown && $ids !== null && array_intersect($state->pageDocumentIds, $ids) === [];
      }
      if (! $pageUnchanged) {
        $state->pageWrites++;
      }
      if ($documentWrite) {
        if (preg_match('/^\s*insert\s+into\s+\S+\s*\([^)]+\)\s*values\s*\(/i', $sql) && ! preg_match('/\b(?:block_id|id)\b/i', $sql)) {
          continue; // A new child/revision document cannot replace this block's metadata.
        }
        $ids = $this->documentIds($event, 'id');
        if ($state->identitiesKnown && ! preg_match('/\bblock_id\b/i', $sql) && $ids !== null && array_intersect($state->documentIds, $ids) === []) {
          continue;
        }
      } elseif ($documentRows) {
        $ids = $this->documentIds($event, 'document_id');
        if ($state->identitiesKnown && $ids !== null && array_intersect($state->documentIds, $ids) === []) {
          continue;
        }
      }
      $state->fields++;
      $state->canonicalFallback = true;
    }
  }

  public function invalidate(): void
  {
    foreach ($this->batches as $batch) {
      $batch->invalidate();
    }
    foreach ($this->contexts as $state) {
      $state->fields++;
      $state->canonicalFallback = true;
      $state->writes++;
      $state->trusted = false;
      $state->pageWrites++;
    }
  }

  public function beforeDocumentRead(Connection $connection, array $modelIds, bool $pages = false): void
  {
    foreach ($this->contexts as $state) {
      if ($state->connection === $connection && in_array($pages ? $state->pageId : $state->id, $modelIds, true)) {
        if ($pages) {
          $state->pageIdentitiesKnown = false;
        } else {
          $state->identitiesKnown = false;
        }
      }
    }
  }

  public function rememberDocuments(Connection $connection, array $modelIds, Collection $documents, bool $pages = false): void
  {
    if (! $pages) {
      foreach ($this->batches as $batch) {
        if ($batch->connection === $connection) {
          $batch->documentIds = array_values(array_unique([...$batch->documentIds, ...$documents->pluck('id')->all()]));
          $batch->documentsKnown = true;
        }
      }
    }
    foreach ($this->contexts as $state) {
      if ($state->connection === $connection && in_array($pages ? $state->pageId : $state->id, $modelIds, true)) {
        if ($pages) {
          $state->pageDocumentIds = $documents->where('page_id', $state->pageId)->pluck('id')->all();
          $state->pageIdentitiesKnown = true;
        } else {
          $state->documentIds = $documents->where('block_id', $state->id)->pluck('id')->all();
          $state->identitiesKnown = true;
        }
      }
    }
  }

  private function current(Block $block): ?RestoredBlockReadState
  {
    $state = $this->contexts[array_key_last($this->contexts)] ?? null;
    if ($state === null || $state->id !== (int) $block->id || ! $this->canReuse()
      || $state->connection !== $block->getConnection() || $state->connection->transactionLevel() === 0
      || $state->connection->getEventDispatcher() !== app('events')) {
      return null;
    }
    if ($state->pdo !== $state->connection->getPdo() || $state->database !== $state->connection->getDatabaseName() || $state->prefix !== $state->connection->getTablePrefix()) {
      return null;
    }

    return $state;
  }

  private function canReuse(): bool
  {
    foreach ([Block::class, BlockType::class, Locale::class, Page::class, PageTranslation::class, BlockTextTranslation::class, BlockButtonTranslation::class, BlockImageTranslation::class, BlockContactFormTranslation::class, BlockPluginTranslation::class, BlockGalleryItemTranslation::class] as $class) {
      if ($class::getEventDispatcher() !== app('events') || ($class::getAllGlobalScopes()[$class] ?? []) !== [] || app('events')->hasListeners('eloquent.retrieved: '.$class)) {
        return false;
      }
    }

    return true;
  }

  private function nativeTarget(Block $block, bool $legacyFallback): ?Block
  {
    if (! $legacyFallback && ! ($block->textTranslations()->exists() || $block->buttonTranslations()->exists()
      || $block->imageTranslations()->exists() || $block->contactFormTranslations()->exists() || $block->galleryItemTranslations()->exists())) {
      return null;
    }

    return $block->fresh([...self::RELATIONS, 'pluginTranslations'])
      ?? throw (new ModelNotFoundException)->setModel(Block::class, [$block->id]);
  }

  private function documentIds(QueryExecuted $event, string $column): ?array
  {
    if (! array_is_list($event->bindings) || preg_match('/\b(?:or|select|union|conflict|duplicate)\b/i', $event->sql)) {
      return null;
    }
    if (preg_match('/\bset\b(.*?)(?:\bwhere\b|$)/is', $event->sql, $set)
      && preg_match('/\b'.$column.'\b/i', $set[1])) {
      return null;
    }
    if (preg_match('/\bwhere\s+["`]?'.$column.'["`]?\s*=\s*\?/i', $event->sql, $match, PREG_OFFSET_CAPTURE)) {
      $index = substr_count(substr($event->sql, 0, $match[0][1]), '?');
      $ids = [$event->bindings[$index] ?? null];
    } elseif (preg_match('/^\s*insert\s+into\s+\S+\s*\(([^)]+)\)\s*values\s*\(/i', $event->sql, $match)) {
      $columns = array_map(fn ($name) => trim($name, " \t\n\r\0\x0B\"`"), explode(',', $match[1]));
      $index = array_search($column, $columns, true);
      if ($index === false || count($event->bindings) % count($columns) !== 0) {
        return null;
      }
      // Expressions/literals do not necessarily align with the binding offsets.
      $tuple = '\(\s*\?'.str_repeat('\s*,\s*\?', count($columns) - 1).'\s*\)';
      if (! preg_match('/^\s*insert\s+into\s+\S+\s*\([^)]+\)\s*values\s*'.$tuple.'(?:\s*,\s*'.$tuple.')*\s*$/i', $event->sql)) {
        return null;
      }
      $ids = [];
      for ($position = $index; $position < count($event->bindings); $position += count($columns)) {
        $ids[] = $event->bindings[$position];
      }
    } else {
      return null;
    }
    foreach ($ids as $id) {
      if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
        return null;
      }
    }

    return array_unique($ids);
  }
}

/** @internal Transient state for a single synchronous restore callback. */
final class RestoredBlockReadState
{
  public int $fields = 0;

  public int $writes = 0;

  public int $fieldStamp = -1;

  public int $relationStamp = -1;

  public bool $trusted = false;

  public bool $identitiesKnown = false;

  public array $documentIds = [];

  public ?Block $model = null;

  public array $canonical = [];

  public bool $canonicalFallback = false;

  public ?int $pageId = null;

  public ?Page $page = null;

  public int $pageWrites = 0;

  public int $pageStamp = -1;

  public bool $pageIdentitiesKnown = false;

  public array $pageDocumentIds = [];

  public readonly mixed $pdo;

  public readonly ?string $database;

  public readonly string $prefix;

  public function __construct(public readonly int $id, public readonly Connection $connection)
  {
    $this->pdo = $connection->getPdo();
    $this->database = $connection->getDatabaseName();
    $this->prefix = $connection->getTablePrefix();
  }
}
