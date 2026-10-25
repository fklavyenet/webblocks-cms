<?php

namespace WebBlocks\Cms\Support\Relational;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Models\SitePromotionRecord;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Revisions\SnapshotContent;
use WebBlocks\Cms\Support\SitePromotion\SitePromotionPlanStore;
use WebBlocks\Cms\Support\Updates\Client\Updates\RunHeartbeat;

final class PersistentSchema
{
  public static function ensure(): void
  {
    OperationalSchema::ensure();
    if (! Schema::hasTable('wbcms_persistent_documents')) {
      Schema::create('wbcms_persistent_documents', function (Blueprint $table): void {
        $table->id();
        foreach (PersistentFields::MODELS as [$column, $owner, $fields]) {
          $column === 'update_request_id' ? $table->uuid($column)->nullable() : $table->unsignedBigInteger($column)->nullable();
          if (Schema::hasTable($owner)) {
            $table->foreign($column, 'persist_owner_'.substr(md5($column), 0, 16))->references('id')->on($owner)->cascadeOnDelete();
          }
          $table->unique([$column, 'attribute'], 'persist_identity_'.substr(md5($column), 0, 16));
        }
        $table->string('attribute', 64);
        $table->string('format', 12);
        $table->unsignedBigInteger('payload_bytes');
        $table->unsignedInteger('node_count');
      });
    }
    if (! Schema::hasTable('wbcms_persistent_nodes')) {
      Schema::create('wbcms_persistent_nodes', function (Blueprint $table): void {
        $table->foreignId('document_id')->constrained('wbcms_persistent_documents')->cascadeOnDelete();
        $table->unsignedInteger('node_index');
        $table->unsignedInteger('parent_index')->nullable();
        $table->unsignedInteger('ordinal');
        $table->string('key_kind', 12)->nullable();
        $table->longText('key_text')->nullable();
        $table->bigInteger('key_integer')->nullable();
        $table->string('kind', 12);
        $table->string('ref_table', 64)->nullable();
        $table->unsignedInteger('ref_index')->nullable();
        $table->string('ref_field', 64)->nullable();
        SettingScalar::columns($table, 'value', 'variant');
        $table->primary(['document_id', 'node_index'], 'persist_node_identity');
      });
    }
    foreach (PersistentDocuments::tables() as $name => $fields) {
      if (Schema::hasTable('wbcms_'.$name)) {
        $existingColumns = Schema::getColumnListing('wbcms_'.$name);
        foreach ($fields as $column => $type) {
          if (! in_array($column, $existingColumns, true)) {
            Schema::table('wbcms_'.$name, fn (Blueprint $table) => SettingScalar::columns($table, $column, $type));
          }
        }

        continue;
      }
      Schema::create('wbcms_'.$name, function (Blueprint $table) use ($name, $fields): void {
        $table->foreignId('document_id')->constrained('wbcms_persistent_documents', indexName: 'persist_record_'.substr(md5($name), 0, 16))->cascadeOnDelete();
        $table->unsignedInteger('item_index');
        $table->longText('item_key')->nullable();
        foreach ($fields as $column => $type) {
          SettingScalar::columns($table, $column, $type);
        }
        $table->primary(['document_id', 'item_index'], 'persist_pk_'.substr(md5($name), 0, 16));
      });
    }
    app(PersistentDocuments::class)->clear();
  }

  public static function migrateAndRetire(bool $retire = true): void
  {
    self::ensure();
    foreach (PersistentFields::MODELS as $name => [$owner, $table, $fields]) {
      if (! Schema::hasTable($table)) {
        continue;
      }
      $class = 'WebBlocks\\Cms\\Models\\'.$name;
      $existing = array_filter(array_keys($fields), fn ($field) => Schema::hasColumn($table, $field));
      foreach ($existing as $attribute) {
        $highWater = DB::table($table)->max('id');
        if ($highWater === null) {
          continue;
        }
        $query = DB::table($table)->where('id', '<=', $highWater);
        if ($name !== 'SystemUpdateRequest') {
          $query->whereNotExists(function ($row) use ($owner, $table, $attribute): void {
          $row->selectRaw('1')->from('wbcms_persistent_documents')->whereColumn($owner, $table.'.id')->where('attribute', $attribute);
          });
        }
        $query->select('id')->chunkById(100, function ($ids) use ($class, $table, $attribute): void {
          foreach ($ids as $identity) {
            app(RunHeartbeat::class)->beat();
            DB::transaction(function () use ($class, $table, $attribute, $identity): void {
              $raw = DB::table($table)->where('id', $identity->id)->lockForUpdate()->first();
              if ($raw === null) {
                return;
              }
              $model = (new $class)->newFromBuilder((array) $raw);
              // Use current canonical model values, including phase-one SQL authority.
              $value = $model->getAttribute($attribute);
              app(PersistentDocuments::class)->write($model, $attribute, $value);
              [$found, $readback] = app(PersistentDocuments::class)->read($model, $attribute);
              $normalized = PersistentFields::definition($model)[2][$attribute] === 'text'
                ? self::normalizeText($value) : $value;
              $actual = PersistentFields::definition($model)[2][$attribute] === 'text' ? self::normalizeText($readback) : $readback;
              if (! $found || ($attribute === 'snapshot' && is_array($value) && is_array($readback)
                ? SnapshotContent::hash($value) !== SnapshotContent::hash($readback) : $normalized !== $actual)) {
                throw new \RuntimeException('Relational migration readback did not match its source.');
              }
              if ($attribute === 'snapshot' && is_array($readback)) {
                $bytes = strlen(json_encode($readback, JSON_THROW_ON_ERROR));
                DB::table($table)->where('id', $identity->id)->update(['snapshot_hash' => SnapshotContent::hash($readback), 'snapshot_bytes' => $bytes]);
                DB::table('wbcms_persistent_documents')->where(PersistentFields::definition($model)[0], $identity->id)->where('attribute', 'snapshot')->update(['payload_bytes' => $bytes]);
              }
            });
          }
        });
      }
      if ($retire && in_array('snapshot', $existing, true)) {
        // Reconcile hashes and wire byte sizes even for documents prepared by the bridge.
        DB::table($table)->select('id')->chunkById(100, function ($ids) use ($class, $table, $owner): void {
          foreach ($ids as $identity) {
            app(RunHeartbeat::class)->beat();
            DB::transaction(function () use ($identity, $class, $table, $owner): void {
              $raw = DB::table($table)->where('id', $identity->id)->lockForUpdate()->first();
              if ($raw === null) {
                return;
              }
              $model = (new $class)->newFromBuilder((array) $raw);
              [$found, $snapshot] = app(PersistentDocuments::class)->read($model, 'snapshot');
              if (! $found) {
                throw new \RuntimeException('Revision snapshot is unavailable; legacy storage was retained.');
              }
              $bytes = strlen(json_encode($snapshot, JSON_THROW_ON_ERROR));
              DB::table($table)->where('id', $identity->id)->update(['snapshot_hash' => is_array($snapshot) ? SnapshotContent::hash($snapshot) : null, 'snapshot_bytes' => $bytes]);
              DB::table('wbcms_persistent_documents')->where($owner, $identity->id)->where('attribute', 'snapshot')->update(['payload_bytes' => $bytes]);
            });
          }
        });
      }
      if ($retire && $existing !== []) {
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn(array_values($existing)));
        app(PersistentDocuments::class)->clear();
      }
    }
    app(InstalledPluginRepository::class)->migrateLegacyState($retire);
    $disk = config('filesystems.disks.'.SitePromotionPlanStore::DISK.'.driver')
      ? Storage::disk(SitePromotionPlanStore::DISK) : null;
    foreach ($disk?->allFiles('plans') ?? [] as $path) {
      if (! str_ends_with($path, '.json')) {
      continue;
      }
      $payload = json_decode((string) $disk->get($path), true);
      if (! is_array($payload) || ! is_string($payload['token'] ?? null)) {
        throw new \RuntimeException('Legacy promotion plan is invalid; its source was retained.');
      }
      $record = SitePromotionRecord::where('token', $payload['token'])->first();
      if ($record === null) {
        $record = SitePromotionRecord::create(['token' => $payload['token'], 'plan' => $payload]);
      }
      if ($retire) {
      $disk->delete($path);
      }
    }
    if (! $retire) {
      return;
    }
    // Phase-two stores already contain canonical Block/Application settings.
    foreach ([Block::class, EmbeddedApplication::class] as $class) {
      $model = new $class;
      $table = $model->getTable();
      if (! Schema::hasTable($table)) {
        continue;
      }
      $columns = array_filter(SettingDefinitions::attributes($model), fn ($attribute) => Schema::hasColumn($table, $attribute));
      if ($columns !== []) {
        StructuredSettingsSchema::ensure();
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn(array_values($columns)));
      }
    }
    if (Schema::hasTable('wbcms_system_settings')) {
      DB::table('wbcms_system_settings')->where('key', 'runtime.scheduler_health')->delete();
    }
    app(PersistentDocuments::class)->clear();
  }

  private static function normalizeText(mixed $value): mixed
  {
    if (! is_string($value)) {
      return $value;
    }
    $decoded = json_decode($value, true);

    return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
  }

  public static function dropFreshTables(): void
  {
    Schema::dropIfExists('wbcms_persistent_nodes');
    foreach (array_reverse(array_keys(PersistentDocuments::tables())) as $name) {
      Schema::dropIfExists('wbcms_'.$name);
    }
    Schema::dropIfExists('wbcms_persistent_documents');
    Schema::dropIfExists('wbcms_plugin_lifecycles');
    Schema::dropIfExists('wbcms_site_promotion_plans');
    app(PersistentDocuments::class)->clear();
  }
}
