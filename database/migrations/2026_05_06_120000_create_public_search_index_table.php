<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('wbcms_public_search_index', function (Blueprint $table) {
      $table->id();
      $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
      $table->foreignId('locale_id')->constrained('wbcms_locales')->cascadeOnDelete();
      $table->foreignId('page_id')->constrained('wbcms_pages')->cascadeOnDelete();
      $table->string('title');
      $table->text('excerpt')->nullable();
      $table->string('url');
      $table->longText('content');
      $table->timestamp('indexed_at')->nullable();
      $table->timestamps();

      $table->unique(['page_id', 'locale_id']);
      $table->index(['site_id', 'locale_id']);
      $table->index('indexed_at');
      $table->index('page_id');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_public_search_index');
  }
};
