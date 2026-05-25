<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('icon_catalog_items', function (Blueprint $table): void {
      $table->id();
      $table->string('source')->default('webblocks-ui');
      $table->string('slug');
      $table->string('label');
      $table->string('css_class');
      $table->json('categories')->nullable();
      $table->json('contexts')->nullable();
      $table->json('keywords')->nullable();
      $table->boolean('is_active')->default(true);
      $table->integer('sort_order')->default(0);
      $table->timestamp('synced_at')->nullable();
      $table->timestamps();

      $table->unique(['source', 'slug']);
      $table->index(['source', 'is_active']);
      $table->index(['sort_order', 'label']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('icon_catalog_items');
  }
};
