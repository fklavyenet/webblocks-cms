<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('wbcms_block_gallery_item_translations', function (Blueprint $table) {
      $table->id();
      $table->foreignId('block_media_id')->constrained('wbcms_block_media')->cascadeOnDelete();
      $table->foreignId('locale_id')->constrained('wbcms_locales')->cascadeOnDelete();
      $table->string('alt_text')->nullable();
      $table->string('caption')->nullable();
      $table->string('overlay_title')->nullable();
      $table->text('overlay_text')->nullable();
      $table->timestamps();

      $table->unique(['block_media_id', 'locale_id'], 'wbcms_bg_item_tr_media_locale_unique');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_block_gallery_item_translations');
  }
};
