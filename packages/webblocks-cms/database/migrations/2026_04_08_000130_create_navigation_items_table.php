<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('wbcms_navigation_items', function (Blueprint $table) {
      $table->id();
      $table->string('menu_name');
      $table->foreignId('parent_id')->nullable()->constrained('wbcms_navigation_items')->nullOnDelete();
      $table->foreignId('page_id')->nullable()->constrained('wbcms_pages')->nullOnDelete();
      $table->string('title');
      $table->string('url')->nullable();
      $table->string('target')->nullable();
      $table->unsignedInteger('sort_order')->default(0);
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_navigation_items');
  }
};
