<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\Database\CmsTable;

return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('webblocks_commerce_product_translations')) {
      return;
    }

    Schema::create('webblocks_commerce_product_translations', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('product_id')->constrained('webblocks_commerce_products')->cascadeOnDelete();
      $table->foreignId('locale_id')->constrained(CmsTable::name('locales'))->cascadeOnDelete();
      // Storefront-facing localized content. Null falls back to the base product.
      $table->string('title')->nullable();
      $table->text('description')->nullable();
      $table->timestamps();

      // One translation row per product per locale; the base product row holds
      // the default/fallback content, so the site is implied by the product.
      $table->unique(['product_id', 'locale_id'], 'wb_commerce_product_locale_unique');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('webblocks_commerce_product_translations');
  }
};
