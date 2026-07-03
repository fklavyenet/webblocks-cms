<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\Database\CmsTable;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('webblocks_commerce_products')) {
      Schema::create('webblocks_commerce_products', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('site_id')->nullable()->constrained(CmsTable::name('sites'))->nullOnDelete();
        $table->foreignId('image_media_id')->nullable()->constrained(CmsTable::name('media'))->nullOnDelete();
        $table->string('title');
        $table->string('slug')->unique();
        $table->text('description')->nullable();
        $table->string('status', 32)->default('draft');
        $table->unsignedBigInteger('price_amount');
        $table->string('currency', 3);
        $table->integer('inventory_quantity')->nullable();
        $table->string('sku')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();

        $table->index(['status', 'created_at']);
        $table->index(['site_id', 'status']);
      });
    }

    if (! Schema::hasTable('webblocks_commerce_orders')) {
      Schema::create('webblocks_commerce_orders', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('site_id')->nullable()->constrained(CmsTable::name('sites'))->nullOnDelete();
        $table->string('order_number', 40)->unique();
        $table->string('customer_email')->nullable();
        $table->string('status', 32)->default('pending');
        $table->unsignedBigInteger('subtotal_amount');
        $table->unsignedBigInteger('total_amount');
        $table->string('currency', 3);
        $table->string('gateway', 64);
        $table->string('gateway_checkout_id')->nullable()->unique();
        $table->string('gateway_payment_id')->nullable();
        $table->string('gateway_customer_id')->nullable();
        $table->timestamp('placed_at')->nullable();
        $table->timestamp('paid_at')->nullable();
        $table->timestamp('cancelled_at')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();

        $table->index(['status', 'created_at']);
        $table->index(['site_id', 'status']);
        $table->index(['gateway', 'created_at']);
      });
    }

    if (! Schema::hasTable('webblocks_commerce_order_items')) {
      Schema::create('webblocks_commerce_order_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('order_id')->constrained('webblocks_commerce_orders')->cascadeOnDelete();
        $table->foreignId('product_id')->nullable()->constrained('webblocks_commerce_products')->nullOnDelete();
        $table->string('title');
        $table->string('sku')->nullable();
        $table->unsignedInteger('quantity')->default(1);
        $table->unsignedBigInteger('unit_amount');
        $table->unsignedBigInteger('total_amount');
        $table->string('currency', 3);
        $table->json('metadata')->nullable();
        $table->timestamps();

        $table->index(['order_id', 'created_at']);
        $table->index(['product_id', 'created_at']);
      });
    }

    if (! Schema::hasTable('webblocks_commerce_payments')) {
      Schema::create('webblocks_commerce_payments', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('order_id')->constrained('webblocks_commerce_orders')->cascadeOnDelete();
        $table->string('gateway', 64);
        $table->string('gateway_payment_id')->nullable();
        $table->string('gateway_checkout_id')->nullable();
        $table->string('status', 32)->default('pending');
        $table->unsignedBigInteger('amount');
        $table->string('currency', 3);
        $table->string('raw_event_id')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();

        $table->index(['order_id', 'created_at']);
        $table->index(['gateway', 'gateway_payment_id']);
        $table->index(['status', 'created_at']);
      });
    }

    if (! Schema::hasTable('webblocks_commerce_webhook_events')) {
      Schema::create('webblocks_commerce_webhook_events', function (Blueprint $table): void {
        $table->id();
        $table->string('gateway', 64);
        $table->string('event_id');
        $table->string('event_type');
        $table->timestamp('processed_at')->nullable();
        $table->string('payload_digest', 64);
        $table->string('status', 32)->default('received');
        $table->text('message')->nullable();
        $table->timestamps();

        $table->unique(['gateway', 'event_id']);
        $table->index(['status', 'created_at']);
        $table->index(['event_type', 'created_at']);
      });
    }
  }

  public function down(): void
  {
    Schema::dropIfExists('webblocks_commerce_webhook_events');
    Schema::dropIfExists('webblocks_commerce_payments');
    Schema::dropIfExists('webblocks_commerce_order_items');
    Schema::dropIfExists('webblocks_commerce_orders');
    Schema::dropIfExists('webblocks_commerce_products');
  }
};
