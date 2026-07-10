<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\Database\CmsTable;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('webblocks_commerce_carts')) {
      Schema::create('webblocks_commerce_carts', function (Blueprint $table): void {
        $table->id();
        $table->string('token', 64)->unique();
        $table->foreignId('site_id')->nullable()->constrained(CmsTable::name('sites'))->nullOnDelete();
        // Store content locale for this cart (drives which product translation is shown).
        $table->string('locale', 10)->nullable();
        $table->string('currency', 3)->nullable();
        $table->string('status', 32)->default('open');
        $table->string('customer_email')->nullable();
        $table->foreignId('converted_order_id')->nullable()->constrained('webblocks_commerce_orders')->nullOnDelete();
        $table->json('metadata')->nullable();
        $table->timestamps();

        $table->index(['status', 'updated_at']);
        $table->index(['site_id', 'status']);
      });
    }

    if (! Schema::hasTable('webblocks_commerce_cart_items')) {
      Schema::create('webblocks_commerce_cart_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('cart_id')->constrained('webblocks_commerce_carts')->cascadeOnDelete();
        $table->foreignId('product_id')->constrained('webblocks_commerce_products')->cascadeOnDelete();
        $table->unsignedInteger('quantity')->default(1);
        $table->string('currency', 3)->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();

        // One line per product; adding the same product again merges quantities.
        $table->unique(['cart_id', 'product_id']);
        $table->index(['cart_id', 'created_at']);
      });
    }
  }

  public function down(): void
  {
    Schema::dropIfExists('webblocks_commerce_cart_items');
    Schema::dropIfExists('webblocks_commerce_carts');
  }
};
