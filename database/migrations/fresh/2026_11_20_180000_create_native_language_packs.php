<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasColumn('wbcms_panel_language_catalogs', 'artifact_checksum')) {
      Schema::table('wbcms_panel_language_catalogs', function (Blueprint $table): void {
        $table->char('artifact_checksum', 64)->nullable();
        $table->index(['language_code', 'artifact_checksum'], 'wbcms_panel_lang_artifact_idx');
      });
    }

    if (! Schema::hasTable('wbcms_native_language_packs')) {
      Schema::create('wbcms_native_language_packs', function (Blueprint $table): void {
        $table->string('code', 12)->primary();
        $table->string('name', 100);
        $table->string('source_version', 64);
        $table->string('release_version', 32);
        $table->char('checksum', 64);
      });
    }

    if (! Schema::hasTable('wbcms_native_language_lines')) {
      Schema::create('wbcms_native_language_lines', function (Blueprint $table): void {
        $table->string('language_code', 12);
        $table->string('catalog_group', 64);
        $table->string('translation_key', 190);
        $table->char('source_hash', 64);
        $table->text('translation');
        $table->primary(['language_code', 'catalog_group', 'translation_key'], 'wbcms_native_lang_lines_pk');
        $table->foreign('language_code')->references('code')->on('wbcms_native_language_packs')->cascadeOnDelete();
      });
    }
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_native_language_lines');
    Schema::dropIfExists('wbcms_native_language_packs');
    if (Schema::hasColumn('wbcms_panel_language_catalogs', 'artifact_checksum')) {
      Schema::table('wbcms_panel_language_catalogs', function (Blueprint $table): void {
        $table->dropIndex('wbcms_panel_lang_artifact_idx');
        $table->dropColumn('artifact_checksum');
      });
    }
  }
};
