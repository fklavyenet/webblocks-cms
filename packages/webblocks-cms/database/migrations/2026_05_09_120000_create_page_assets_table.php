<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('page_assets', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('page_id')->constrained()->cascadeOnDelete();
      $table->string('type', 16);
      $table->string('path');
      $table->string('load_position', 32);
      $table->boolean('is_defer')->default(true);
      $table->boolean('is_async')->default(false);
      $table->boolean('is_module')->default(false);
      $table->boolean('is_enabled')->default(true);
      $table->unsignedInteger('sort_order')->default(0);
      $table->timestamps();

      $table->index('page_id');
      $table->index(['page_id', 'type', 'is_enabled', 'sort_order']);
      $table->unique(['page_id', 'type', 'path']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('page_assets');
  }
};
