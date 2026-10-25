<?php

namespace WebBlocks\Cms\Support\Relational;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\CmsModel;

class PersistentDocuments
{
  private ?bool $available = null;

  private array $retired = [];

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
    foreach ($models->chunk(100) as $chunk) {
      $documents = DB::table('wbcms_persistent_documents')->whereIn($owner, $chunk->modelKeys())->get();
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

  private function values(Collection $documents): array
  {
    if ($documents->isEmpty()) {
      return [];
    }
    $ids = $documents->pluck('id');
    $nodes = DB::table('wbcms_persistent_nodes')->whereIn('document_id', $ids)->get()->groupBy('document_id')->map(fn ($group) => $group->sortBy('node_index')->values());
    $used = $nodes->flatten(1)->where('kind', 'column')->pluck('ref_table')->unique();
    $rows = [];
    foreach ($used as $table) {
      if (! isset(self::tables()[$table])) {
        throw new \RuntimeException('Relational document references an invalid record type.');
      }
      $rows[$table] = DB::table('wbcms_'.$table)->whereIn('document_id', $ids)->get()->groupBy('document_id');
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

  public function write(CmsModel $model, string $attribute, mixed $value): void
  {
    $definition = PersistentFields::definition($model);
    if ($definition === null || ! isset($definition[2][$attribute])) {
      throw new \InvalidArgumentException('Unknown persistent attribute.');
    }
    $bytes = is_string($value) ? strlen($value) : strlen(json_encode($value, JSON_THROW_ON_ERROR));
    $format = 'value';
    if ($definition[2][$attribute] === 'text' && is_string($value)) {
      $decoded = json_decode($value);
      if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || $decoded instanceof \stdClass)) {
        $format = 'json';
        $value = $decoded;
      }
    }
    [$nodes, $records] = SettingTree::encode($value, false, $attribute, $this->selector($attribute), $attribute === 'snapshot');
    $identity = [$definition[0] => $model->getKey(), 'attribute' => $attribute];
    $existing = DB::table('wbcms_persistent_documents')->where($identity)->lockForUpdate()->first(['id']);
    $metadata = ['format' => $format, 'payload_bytes' => $bytes, 'node_count' => count($nodes)];
    if ($existing === null) {
      // A new document has no prior child rows to remove.
      $id = (int) DB::table('wbcms_persistent_documents')->insertGetId($identity + $metadata);
    } else {
      $id = (int) $existing->id;
      DB::table('wbcms_persistent_documents')->where('id', $id)->update($metadata);
      DB::table('wbcms_persistent_nodes')->where('document_id', $id)->delete();
      $tables = $attribute === 'snapshot' ? array_keys(SnapshotFields::tables()) : ['persistent_items', 'persistent_metadata', 'import_identity_maps', 'import_copied_files'];
      foreach ($tables as $table) {
        DB::table('wbcms_'.$table)->where('document_id', $id)->delete();
      }
    }
    foreach ($records as $table => $items) {
      $columns = [];
      foreach ($items as $item) {
        $columns += array_fill_keys(array_keys($item), null);
      }
      $batchSize = max(1, min(100, intdiv(900, count($columns) + 1)));
      foreach (array_chunk($items, $batchSize) as $batch) {
        DB::table('wbcms_'.$table)->insert(array_map(fn ($item) => ['document_id' => $id, ...array_replace($columns, $item)], $batch));
      }
    }
    foreach (array_chunk($nodes, 300) as $chunk) {
      DB::table('wbcms_persistent_nodes')->insert(array_map(fn ($node) => ['document_id' => $id, ...$node], $chunk));
    }
  }
}
