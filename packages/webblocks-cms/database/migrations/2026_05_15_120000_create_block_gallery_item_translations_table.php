<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('block_gallery_item_translations', function (Blueprint $table) {
      $table->id();
      $table->foreignId('block_media_id')->constrained('block_media')->cascadeOnDelete();
      $table->foreignId('locale_id')->constrained('locales')->cascadeOnDelete();
      $table->string('alt_text')->nullable();
      $table->string('caption')->nullable();
      $table->string('overlay_title')->nullable();
      $table->text('overlay_text')->nullable();
      $table->timestamps();

      $table->unique(['block_media_id', 'locale_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('block_gallery_item_translations');
  }
};
