<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Per-locale copy for blocks that plugins declare.
 *
 * Every existing translation family is a table with fixed columns — `text` has
 * title/eyebrow/subtitle/content/meta, `contact_form` has its own five — which works
 * because core knows every field before the table is written. A plugin's block is
 * declared at install time by a package core has never seen, so there is no column
 * to add and no migration to add it in.
 *
 * Hence rows rather than columns: one row per block, locale and field name. It gives
 * up the type safety a column has and buys the only thing that matters here, which is
 * that a plugin can name its own fields without core shipping a release first.
 */
return new class extends Migration
{
  public function up(): void
  {
    Schema::create('wbcms_block_plugin_translations', function (Blueprint $table) {
      $table->id();
      $table->foreignId('block_id')->constrained('wbcms_blocks')->cascadeOnDelete();
      $table->foreignId('locale_id')->constrained('wbcms_locales')->cascadeOnDelete();

      /*
       * The field name the plugin declared. Length-capped rather than a text column
       * because it is an identifier, and it is part of the unique key below.
       */
      $table->string('field', 100);
      $table->text('value')->nullable();
      $table->timestamps();

      $table->unique(['block_id', 'locale_id', 'field'], 'wbcms_block_plugin_tr_unique');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_block_plugin_translations');
  }
};
