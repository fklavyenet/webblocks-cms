<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('wbcms_extensions_search_publications', function (Blueprint $table): void {
      $table->foreignId('rule_id')->primary()->constrained('wbcms_automatic_page_rules')->cascadeOnDelete();
      $table->unsignedBigInteger('revision');
      $table->unsignedBigInteger('sequence');
      $table->timestamp('received_at');
    });
    Schema::create('wbcms_extensions_search_entries', function (Blueprint $table): void {
      $table->foreignId('rule_id')->constrained('wbcms_extensions_search_publications', 'rule_id')->cascadeOnDelete();
      $table->string('record_key', 150);
      $table->string('locale', 16);
      $table->text('title');
      $table->text('description');
      $table->primary(['rule_id', 'record_key', 'locale'], 'wbcms_extensions_search_entry_pk');
      $table->index(['rule_id', 'locale']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_extensions_search_entries');
    Schema::dropIfExists('wbcms_extensions_search_publications');
  }
};
