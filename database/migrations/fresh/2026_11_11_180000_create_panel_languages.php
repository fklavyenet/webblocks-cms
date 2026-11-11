<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_panel_languages')) {
      Schema::create('wbcms_panel_languages', function (Blueprint $table): void {
        $table->string('code', 12)->primary();
        $table->unsignedBigInteger('active_catalog_id')->nullable();
      });
    }

    if (! Schema::hasTable('wbcms_panel_language_catalogs')) {
      Schema::create('wbcms_panel_language_catalogs', function (Blueprint $table): void {
        $table->id();
        $table->string('language_code', 12);
        $table->string('name', 100);
        $table->string('source_version', 64);
        $table->timestamp('created_at');
        $table->foreign('language_code')->references('code')->on('wbcms_panel_languages')->cascadeOnDelete();
      });
    }

    if (! Schema::hasTable('wbcms_panel_language_lines')) {
      Schema::create('wbcms_panel_language_lines', function (Blueprint $table): void {
        $table->foreignId('catalog_id')->constrained('wbcms_panel_language_catalogs')->cascadeOnDelete();
        $table->string('translation_key', 190);
        $table->char('source_hash', 64);
        $table->text('translation');
        $table->primary(['catalog_id', 'translation_key']);
      });
    }
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_panel_language_lines');
    Schema::dropIfExists('wbcms_panel_language_catalogs');
    Schema::dropIfExists('wbcms_panel_languages');
  }
};
