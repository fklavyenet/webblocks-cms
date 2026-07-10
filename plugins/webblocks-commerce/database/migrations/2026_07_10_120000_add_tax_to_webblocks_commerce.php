<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('webblocks_commerce_products') && ! Schema::hasColumn('webblocks_commerce_products', 'tax_class')) {
      Schema::table('webblocks_commerce_products', function (Blueprint $table): void {
        $table->string('tax_class', 32)->default('standard')->after('currency');
      });
    }

    if (Schema::hasTable('webblocks_commerce_orders')) {
      Schema::table('webblocks_commerce_orders', function (Blueprint $table): void {
        if (! Schema::hasColumn('webblocks_commerce_orders', 'tax_amount')) {
          $table->unsignedBigInteger('tax_amount')->default(0)->after('total_amount');
        }
        if (! Schema::hasColumn('webblocks_commerce_orders', 'tax_rate')) {
          // Applied VAT rate in basis points (1900 = 19.00%).
          $table->unsignedInteger('tax_rate')->default(0)->after('tax_amount');
        }
        if (! Schema::hasColumn('webblocks_commerce_orders', 'tax_country')) {
          $table->string('tax_country', 2)->nullable()->after('tax_rate');
        }
        if (! Schema::hasColumn('webblocks_commerce_orders', 'prices_include_tax')) {
          $table->boolean('prices_include_tax')->default(true)->after('tax_country');
        }
      });
    }

    if (Schema::hasTable('webblocks_commerce_order_items')) {
      Schema::table('webblocks_commerce_order_items', function (Blueprint $table): void {
        if (! Schema::hasColumn('webblocks_commerce_order_items', 'tax_amount')) {
          $table->unsignedBigInteger('tax_amount')->default(0)->after('total_amount');
        }
        if (! Schema::hasColumn('webblocks_commerce_order_items', 'tax_rate')) {
          $table->unsignedInteger('tax_rate')->default(0)->after('tax_amount');
        }
        if (! Schema::hasColumn('webblocks_commerce_order_items', 'tax_class')) {
          $table->string('tax_class', 32)->nullable()->after('tax_rate');
        }
      });
    }
  }

  public function down(): void
  {
    if (Schema::hasTable('webblocks_commerce_order_items')) {
      Schema::table('webblocks_commerce_order_items', function (Blueprint $table): void {
        $table->dropColumn(['tax_amount', 'tax_rate', 'tax_class']);
      });
    }

    if (Schema::hasTable('webblocks_commerce_orders')) {
      Schema::table('webblocks_commerce_orders', function (Blueprint $table): void {
        $table->dropColumn(['tax_amount', 'tax_rate', 'tax_country', 'prices_include_tax']);
      });
    }

    if (Schema::hasTable('webblocks_commerce_products')) {
      Schema::table('webblocks_commerce_products', function (Blueprint $table): void {
        $table->dropColumn('tax_class');
      });
    }
  }
};
