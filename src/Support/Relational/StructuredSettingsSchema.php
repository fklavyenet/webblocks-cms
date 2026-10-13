<?php

namespace WebBlocks\Cms\Support\Relational;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\EmbeddedApplication;

final class StructuredSettingsSchema
{
  public static function ensure(): void
  {
    if (! Schema::hasTable('wbcms_setting_documents')) {
      Schema::create('wbcms_setting_documents', function (Blueprint $table): void {
        $table->id();
        foreach (['block_id' => 'wbcms_blocks', 'application_id' => 'wbcms_embedded_applications'] as $column => $owner) {
          if (Schema::hasTable($owner)) {
            $table->foreignId($column)->nullable()->constrained($owner)->cascadeOnDelete();
          } else {
            $table->unsignedBigInteger($column)->nullable();
          }
        }
        $table->string('attribute', 64);
        $table->string('format', 12);
        $table->unsignedBigInteger('payload_bytes')->default(0);
        $table->unsignedInteger('node_count')->default(0);
        $table->unique(['block_id', 'attribute'], 'setting_document_block_field');
        $table->unique(['application_id', 'attribute'], 'setting_document_application_field');
      });
    }
    if (! Schema::hasTable('wbcms_setting_nodes')) {
      Schema::create('wbcms_setting_nodes', function (Blueprint $table): void {
        $table->foreignId('document_id')->constrained('wbcms_setting_documents')->cascadeOnDelete();
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
        $table->primary(['document_id', 'node_index'], 'setting_node_identity');
        $table->index(['document_id', 'parent_index', 'ordinal'], 'setting_node_parent_order');
      });
    }
    foreach (SettingDefinitions::tables() as $name => $fields) {
      if (Schema::hasTable('wbcms_'.$name)) {
        continue;
      }
      Schema::create('wbcms_'.$name, function (Blueprint $table) use ($name, $fields): void {
        $table->foreignId('document_id')->constrained('wbcms_setting_documents', indexName: 'setting_owner_'.substr(md5($name), 0, 16))->cascadeOnDelete();
        $table->unsignedInteger('item_index');
        $table->longText('item_key')->nullable();
        foreach ($fields as $column => $type) {
          SettingScalar::columns($table, $column, $type);
        }
        $table->primary(['document_id', 'item_index'], 'setting_record_'.substr(md5($name), 0, 16));
        if ($name === 'block_content_collections') {
          if (Schema::hasTable('wbcms_blocks')) {
            $table->foreignId('resolved_template_id')->nullable()->constrained('wbcms_blocks', indexName: 'block_collection_template_fk')->nullOnDelete();
          } else {
            $table->unsignedBigInteger('resolved_template_id')->nullable();
          }
        } elseif ($name === 'application_setting_options') {
          $table->unsignedInteger('field_index');
          $table->unsignedInteger('position');
          $table->foreign(['document_id', 'field_index'], 'application_option_field_fk')->references(['document_id', 'item_index'])->on('wbcms_application_setting_fields')->cascadeOnDelete();
        }
      });
    }
    app(StructuredSettings::class)->clearAvailability();
    self::backfill();
  }

  public static function dropFreshTables(): void
  {
    Schema::dropIfExists('wbcms_setting_nodes');
    foreach (array_reverse(array_keys(SettingDefinitions::tables())) as $name) {
      Schema::dropIfExists('wbcms_'.$name);
    }
    Schema::dropIfExists('wbcms_setting_documents');
    app(StructuredSettings::class)->clearAvailability();
  }

  private static function backfill(): void
  {
    foreach ([Block::class, EmbeddedApplication::class] as $class) {
      $model = new $class;
      if (! Schema::hasTable($model->getTable())) {
        continue;
      }
      $attributes = array_values(array_filter(SettingDefinitions::attributes($model), fn (string $attribute) => Schema::hasColumn($model->getTable(), $attribute)));
      $owner = $model instanceof Block ? 'block_id' : 'application_id';
      $highWater = (int) DB::table($model->getTable())->max('id');
      DB::table($model->getTable())->where('id', '<=', $highWater)->select('id')->chunkById(200, function ($ids) use ($model, $class, $attributes, $owner): void {
        DB::transaction(function () use ($ids, $model, $class, $attributes, $owner): void {
          foreach ($ids as $identity) {
            // Read one payload at a time: no sorted JSON or growing payload batch.
            $row = DB::table($model->getTable())->where('id', $identity->id)->lockForUpdate()->first(['id', ...$attributes]);
            if ($row === null) {
              continue;
            }
            $existing = DB::table('wbcms_setting_documents')->where($owner, $row->id)->pluck('attribute')->all();
            $record = (new $class)->newFromBuilder((array) $row);
            foreach (array_diff($attributes, $existing) as $attribute) {
              $value = $record instanceof Block ? $record->serializedSettings() : $record->getAttribute($attribute);
              app(StructuredSettings::class)->write($record, $attribute, $value);
            }
          }
        });
      });
    }
  }
}
