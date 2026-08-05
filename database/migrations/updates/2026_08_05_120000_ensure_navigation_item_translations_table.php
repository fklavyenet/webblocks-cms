<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_navigation_item_translations')) {
      Schema::create('wbcms_navigation_item_translations', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('navigation_item_id')->constrained('wbcms_navigation_items')->cascadeOnDelete();
        $table->foreignId('locale_id')->constrained('wbcms_locales')->cascadeOnDelete();
        $table->string('title')->nullable();
        $table->timestamps();

        $table->unique(['navigation_item_id', 'locale_id'], 'wbcms_nav_item_tr_item_locale_unique');
      });
    }
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_navigation_item_translations');
  }
};
