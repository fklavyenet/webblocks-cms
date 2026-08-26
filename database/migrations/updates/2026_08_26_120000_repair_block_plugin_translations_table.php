<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The plugin translation migration shipped before its table was included in the
 * fresh-install baseline and before the model table was added to CmsTable. Repair
 * installations that consequently reached the runtime without the physical table.
 */
return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('wbcms_block_plugin_translations')) {
      return;
    }

    Schema::create('wbcms_block_plugin_translations', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('block_id')->constrained('wbcms_blocks')->cascadeOnDelete();
      $table->foreignId('locale_id')->constrained('wbcms_locales')->cascadeOnDelete();
      $table->string('field', 100);
      $table->text('value')->nullable();
      $table->timestamps();
      $table->unique(['block_id', 'locale_id', 'field'], 'wbcms_block_plugin_tr_unique');
    });
  }

  public function down(): void
  {
    // Repair migrations must not remove a table that may predate this migration.
  }
};
