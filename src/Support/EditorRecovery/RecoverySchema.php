<?php

namespace WebBlocks\Cms\Support\EditorRecovery;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\Relational\SettingScalar;

final class RecoverySchema
{
  public static function migrate(): void
  {
    if (! Schema::hasTable('wbcms_editor_recoveries')) {
      Schema::create('wbcms_editor_recoveries', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->foreignId('page_id')->constrained('wbcms_pages')->cascadeOnDelete();
        $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
        $table->unsignedBigInteger('block_id')->nullable();
        $table->string('locale', 20);
        $table->uuid('editing_session');
        $table->string('base_fingerprint', 64);
        $table->string('payload_hash', 64)->nullable();
        $table->unsignedInteger('sequence')->default(0);
        $table->boolean('cleared')->default(false);
        $table->string('restore_token_hash', 64)->nullable();
        $table->timestamp('restore_expires_at')->nullable();
        $table->timestamp('expires_at')->index();
        $table->timestamps();
        $table->unique(['user_id', 'editing_session'], 'editor_recovery_owner_session');
        $table->index(['user_id', 'page_id', 'block_id', 'locale'], 'editor_recovery_context');
      });
    }
    if (! Schema::hasTable('wbcms_editor_recovery_values')) {
      Schema::create('wbcms_editor_recovery_values', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('recovery_id')->constrained('wbcms_editor_recoveries')->cascadeOnDelete();
        $table->unsignedInteger('position');
        $table->string('field_name', 190);
        $table->string('format', 12);
        $table->longText('text_value')->nullable();
        $table->unique(['recovery_id', 'position'], 'editor_recovery_value_order');
      });
    }
    if (! Schema::hasTable('wbcms_editor_recovery_nodes')) {
      Schema::create('wbcms_editor_recovery_nodes', function (Blueprint $table): void {
        $table->foreignId('value_id')->constrained('wbcms_editor_recovery_values')->cascadeOnDelete();
        $table->unsignedInteger('node_index');
        $table->unsignedInteger('parent_index')->nullable();
        $table->unsignedInteger('ordinal');
        $table->string('key_kind', 12)->nullable();
        $table->text('key_text')->nullable();
        $table->bigInteger('key_integer')->nullable();
        $table->string('kind', 12);
        $table->string('ref_table', 64)->nullable();
        $table->unsignedInteger('ref_index')->nullable();
        $table->string('ref_field', 64)->nullable();
        SettingScalar::columns($table, 'value', 'variant');
        $table->primary(['value_id', 'node_index'], 'editor_recovery_node_identity');
      });
    }
  }
}
