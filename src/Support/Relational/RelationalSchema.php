<?php

namespace WebBlocks\Cms\Support\Relational;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;

final class RelationalSchema
{
  public static function ensure(): void
  {
    foreach ([DocumentFields::page(), DocumentFields::block(), DocumentFields::notification()] as $definition) {
      if (! Schema::hasTable($definition['owner_table']) || Schema::hasTable($definition['table'])) {
        continue;
      }
      Schema::create($definition['table'], function (Blueprint $table) use ($definition): void {
        $table->foreignId($definition['owner'])->primary()->constrained($definition['owner_table'])->cascadeOnDelete();
        $table->string('document_kind', 10);
        foreach ($definition['groups'] as $group) {
          $table->string($group.'_kind', 10)->nullable();
        }
        foreach ($definition['fields'] as [$column, $type]) {
          $field = match ($type) {
            'integer' => $table->bigInteger($column)->nullable(),
            'boolean' => $table->boolean($column)->nullable(),
            'text' => $table->text($column)->nullable(),
            default => $table->string($column, 190)->nullable(),
          };
          if (in_array($type, ['string', 'text'], true) && Schema::getConnection()->getDriverName() === 'mysql') {
            $field->collation('utf8mb4_bin');
          }
          $table->string($column.'_kind', 10)->nullable();
          $table->text($column.'_text')->nullable();
        }
        foreach ($definition['lists'] as $path) {
          $table->string(str_replace('.', '_', $path).'_kind', 10)->nullable();
        }
        foreach ($definition['relations'] as $column => $target) {
          if (Schema::hasTable($target)) {
            $table->foreignId($column)->nullable()->constrained($target, indexName: 'runtime_'.substr(md5($definition['table'].$column), 0, 16))->nullOnDelete();
          } else {
            $table->unsignedBigInteger($column)->nullable();
          }
        }
        if ($definition['owner'] === 'page_id') {
          $table->index(['staged_source_reference', 'staged_type', 'staged_state'], 'page_runtime_staged_lookup');
          $table->index('shared_slot_reference', 'page_runtime_shared_lookup');
          $table->index('revision_restore_candidate_kind', 'page_runtime_restore_lookup');
          $table->index('layout_handle', 'page_runtime_layout_lookup');
        } elseif ($definition['owner'] === 'block_id') {
          $table->index('application_handle', 'block_application_handle_lookup');
        }
      });
    }
    if (Schema::hasTable('wbcms_pages') && ! Schema::hasTable('wbcms_page_runtime_items')) {
      Schema::create('wbcms_page_runtime_items', function (Blueprint $table): void {
        $table->foreignId('page_id')->constrained('wbcms_pages')->cascadeOnDelete();
        $table->string('collection', 80);
        $table->unsignedInteger('position');
        $table->string('value', 190);
        $table->primary(['page_id', 'collection', 'position'], 'page_runtime_item_order');
      });
    }
    if (Schema::hasTable('wbcms_cms_api_tokens')) {
      foreach (TokenRelations::LISTS as $attribute => [$name, $type]) {
        if (! Schema::hasColumn('wbcms_cms_api_tokens', $attribute.'_mode')) {
          Schema::table('wbcms_cms_api_tokens', fn (Blueprint $table) => $table->string($attribute.'_mode', 10)->nullable());
        }
        if (! Schema::hasTable($name)) {
          Schema::create($name, function (Blueprint $table) use ($type): void {
            $table->foreignId('token_id')->constrained('wbcms_cms_api_tokens')->cascadeOnDelete();
            $table->unsignedInteger('position');
            if ($type === 'integer') {
              $table->bigInteger('value');
              $table->text('original_text')->nullable();
              $table->foreignId('resolved_site_id')->nullable()->constrained('wbcms_sites')->nullOnDelete();
            } else {
              $table->string('value', 190);
            }
            $table->primary(['token_id', 'position']);
            $table->index('value');
          });
        }
      }
    }
    if (! Schema::hasTable('wbcms_scheduler_health')) {
      Schema::create('wbcms_scheduler_health', function (Blueprint $table): void {
        $table->string('key', 80)->primary();
        $table->text('scheduler_last_seen_at')->nullable();
        $table->text('notifications_started_at')->nullable();
        $table->text('notifications_completed_at')->nullable();
        $table->text('notifications_failed_at')->nullable();
      });
    }
    app(RelationalDocuments::class)->clearAvailability();
    app(TokenRelations::class)->clearAvailability();
    self::backfill();
  }

  public static function dropFreshTables(): void
  {
    foreach (['wbcms_page_runtime_items', ...array_column(TokenRelations::LISTS, 0), DocumentFields::page()['table'], DocumentFields::block()['table'], DocumentFields::notification()['table'], 'wbcms_scheduler_health'] as $name) {
      Schema::dropIfExists($name);
    }
    app(RelationalDocuments::class)->clearAvailability();
    app(TokenRelations::class)->clearAvailability();
  }

  private static function backfill(): void
  {
    foreach ([[Page::class, 'settings'], [Block::class, 'settings'], [Site::class, 'notification_settings']] as [$modelClass, $attribute]) {
      $model = new $modelClass;
      $definition = DocumentFields::for($model, $attribute);
      if (! Schema::hasTable($model->getTable()) || ! Schema::hasColumn($model->getTable(), $attribute)) {
        continue;
      }
      $highWater = (int) DB::table($model->getTable())->max('id');
      DB::table($model->getTable())->where('id', '<=', $highWater)->select('id')->chunkById(200, function ($ids) use ($modelClass, $model, $definition, $attribute): void {
        DB::transaction(function () use ($ids, $modelClass, $model, $definition, $attribute): void {
          $rows = DB::table($model->getTable())->whereIn('id', $ids->pluck('id'))->lockForUpdate()->get(['id', $attribute]);
          foreach ($rows as $row) {
            if (DB::table($definition['table'])->where($definition['owner'], $row->id)->exists()) {
              continue;
            }
            $owner = (new $modelClass)->newFromBuilder(['id' => $row->id]);
            app(RelationalDocuments::class)->write($owner, $definition, app(RelationalDocuments::class)->decoded($row->{$attribute}));
          }
        });
      });
    }
    if (Schema::hasTable('wbcms_cms_api_tokens')) {
      DB::table('wbcms_cms_api_tokens')->where('id', '<=', (int) DB::table('wbcms_cms_api_tokens')->max('id'))->select('id')->chunkById(200, function ($ids): void {
        DB::transaction(function () use ($ids): void {
          foreach (DB::table('wbcms_cms_api_tokens')->whereIn('id', $ids->pluck('id'))->lockForUpdate()->get() as $row) {
            $token = (new CmsApiToken)->newFromBuilder(['id' => $row->id]);
            foreach (TokenRelations::LISTS as $attribute => $_) {
              if ($row->{$attribute.'_mode'} === null) {
                app(TokenRelations::class)->write($token, $attribute, app(RelationalDocuments::class)->decoded($row->{$attribute}));
              }
            }
          }
        });
      });
    }
    if (Schema::hasTable('wbcms_system_settings')) {
      $raw = DB::table('wbcms_system_settings')->where('key', 'runtime.scheduler_health')->value('value');
      $state = is_string($raw) ? json_decode($raw, true) : null;
      if (is_array($state) && ! DB::table('wbcms_scheduler_health')->where('key', 'notifications')->exists()) {
        $values = array_filter(array_intersect_key($state, array_flip(['scheduler_last_seen_at', 'notifications_started_at', 'notifications_completed_at', 'notifications_failed_at'])), fn ($value) => $value === null || is_string($value));
        DB::table('wbcms_scheduler_health')->insert(['key' => 'notifications', ...$values]);
      }
    }
  }
}
