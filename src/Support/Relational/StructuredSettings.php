<?php

namespace WebBlocks\Cms\Support\Relational;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\CmsModel;

class StructuredSettings
{
  private ?bool $available = null;

  private array $columns = [];

  public function available(): bool
  {
    if (! app()->bound('db.schema')) {
      return false;
    }

    return $this->available ??= Schema::hasTable('wbcms_setting_documents');
  }

  public function clearAvailability(): void
  {
    $this->available = null;
    $this->columns = [];
  }

  public function read(CmsModel $model, string $attribute): array
  {
    if (! $model->exists || ! $this->available()) {
      return [false, null];
    }
    $documents = DB::table('wbcms_setting_documents')->where($model instanceof Block ? 'block_id' : 'application_id', $model->getKey())->where('attribute', $attribute)->get();
    if ($documents->isEmpty()) {
      return [false, null];
    }

    return [true, $this->values($documents)[$documents->first()->id]];
  }

  /** Batch a collection's SQL settings rather than adding queries per rendered block. */
  public function prime(Collection $models): void
  {
    $models = $models->filter(fn (CmsModel $model) => $model->getKey() !== null && ! $model->structuredSettingsPrimed());
    if ($models->isEmpty() || ! $this->available()) {
      return;
    }
    $owner = $models->first() instanceof Block ? 'block_id' : 'application_id';
    foreach ($models->chunk(200) as $chunk) {
      $documents = DB::table('wbcms_setting_documents')->whereIn($owner, $chunk->modelKeys())->get();
      $owners = $chunk->keyBy(fn (CmsModel $model) => $model->getKey());
      $pending = collect();
      $bytes = 0;
      $nodeCount = 0;
      $flush = function () use (&$pending, &$bytes, &$nodeCount, $owners, $owner): void {
        $values = $this->values($pending);
        foreach ($pending as $document) {
          $owners[$document->{$owner}]->seedRelationalValue($document->attribute, $values[$document->id]);
        }
        $pending = collect();
        $bytes = $nodeCount = 0;
      };
      foreach ($documents as $document) {
        if ($pending->isNotEmpty() && ($pending->count() >= 200 || $bytes + (int) $document->payload_bytes > 2 * 1024 * 1024 || $nodeCount + (int) $document->node_count > 5000)) {
          $flush();
        }
        $pending->push($document);
        $bytes += (int) $document->payload_bytes;
        $nodeCount += (int) $document->node_count;
      }
      if ($pending->isNotEmpty()) {
        $flush();
      }
      foreach ($chunk as $model) {
        $model->markStructuredSettingsPrimed();
      }
    }
  }

  private function values(Collection $documents): array
  {
    if ($documents->isEmpty()) {
      return [];
    }
    $ids = $documents->pluck('id');
    $nodes = DB::table('wbcms_setting_nodes')->whereIn('document_id', $ids)->get()->groupBy('document_id')->map(fn ($group) => $group->sortBy('node_index')->values());
    $used = $nodes->flatten(1)->where('kind', 'column')->pluck('ref_table')->unique()->all();
    $records = [];
    foreach (array_keys(SettingDefinitions::tables()) as $table) {
      if (in_array($table, $used, true)) {
        $records[$table] = DB::table('wbcms_'.$table)->whereIn('document_id', $ids)->get()->groupBy('document_id');
      }
    }
    $references = in_array('application_reference', $used, true)
      ? DB::table('wbcms_block_application_references')->whereIn('block_id', $documents->pluck('block_id')->filter())->get()->keyBy('block_id') : collect();
    $values = [];
    foreach ($documents as $document) {
      $rows = [];
      foreach ($records as $table => $groups) {
        $rows[$table] = $groups->get($document->id, collect())->keyBy('item_index')->all();
      }
      $value = SettingTree::decode($nodes->get($document->id, collect())->all(), $rows, $references->get($document->block_id));
      $values[$document->id] = $document->format === 'json'
        ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : $value;
    }

    return $values;
  }

  public function captureDefaults(CmsModel $model): void
  {
    if (! $this->available()) {
      return;
    }
    $attributes = array_values(array_filter(array_diff(SettingDefinitions::attributes($model), array_keys($model->getAttributes())), function (string $attribute) use ($model): bool {
      $key = $model->getTable().'.'.$attribute;

      return $this->columns[$key] ??= Schema::hasColumn($model->getTable(), $attribute);
    }));
    if ($attributes === []) {
      return;
    }
    $raw = DB::table($model->getTable())->where('id', $model->getKey())->first(['id', ...$attributes]);
    $record = $model->newFromBuilder((array) $raw);
    foreach ($attributes as $attribute) {
      [$exists] = $this->read($record, $attribute);
      if ($exists) {
        continue;
      }
      $value = $record->getAttribute($attribute);
      if ($record instanceof Block && app(RelationalDocuments::class)->available(DocumentFields::block())) {
        app(RelationalDocuments::class)->write($record, DocumentFields::block(), app(RelationalDocuments::class)->decoded($value));
      }
      $this->write($record, $attribute, $value);
    }
  }

  public function write(CmsModel $model, string $attribute, mixed $value): void
  {
    $bytes = is_string($value) ? strlen($value) : strlen(json_encode($value, JSON_THROW_ON_ERROR));
    $format = 'value';
    if ($model instanceof Block && is_string($value)) {
      $decoded = json_decode($value);
      $valid = json_last_error() === JSON_ERROR_NONE;
      $format = $valid ? 'json' : 'raw';
      $value = $valid ? $decoded : $value;
    }
    [$nodes, $records] = SettingTree::encode($value, $model instanceof Block, $attribute);
    $owner = $model instanceof Block ? 'block_id' : 'application_id';
    $identity = [$owner => $model->getKey(), 'attribute' => $attribute];
    DB::table('wbcms_setting_documents')->updateOrInsert($identity, ['format' => $format, 'payload_bytes' => $bytes, 'node_count' => count($nodes)]);
    $documentId = (int) DB::table('wbcms_setting_documents')->where($identity)->value('id');
    DB::table('wbcms_setting_nodes')->where('document_id', $documentId)->delete();
    // Options depend on field definitions; remove children first, insert them last.
    $tables = array_keys(SettingDefinitions::tables());
    foreach (array_reverse($tables) as $table) {
      DB::table('wbcms_'.$table)->where('document_id', $documentId)->delete();
    }
    foreach ($tables as $table) {
      foreach ($records[$table] ?? [] as $row) {
        if ($table === 'block_content_collections') {
          $id = $row['template_block_id'] ?? null;
          $row['resolved_template_id'] = $id === null ? null : DB::table('wbcms_blocks')->where('id', $id)->value('id');
        }
        DB::table('wbcms_'.$table)->insert(['document_id' => $documentId, ...$row]);
      }
    }
    foreach (array_chunk($nodes, 500) as $batch) {
      DB::table('wbcms_setting_nodes')->insert(array_map(fn (array $node) => ['document_id' => $documentId, ...$node], $batch));
    }
  }
}
