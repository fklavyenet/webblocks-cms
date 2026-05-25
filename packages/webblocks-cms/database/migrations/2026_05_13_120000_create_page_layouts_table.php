<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\Pages\PageLayoutCatalog;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('page_layouts', function (Blueprint $table) {
      $table->id();
      $table->string('handle')->unique();
      $table->string('name');
      $table->text('description')->nullable();
      $table->boolean('is_system')->default(false);
      $table->boolean('is_active')->default(true);
      $table->unsignedInteger('sort_order')->default(0);
      $table->string('shell_type')->default('default');
      $table->json('slot_schema')->nullable();
      $table->json('wrapper_schema')->nullable();
      $table->timestamps();

      $table->index(['is_active', 'sort_order']);
    });

    $now = now();
    $rows = collect(PageLayoutCatalog::definitions())
      ->map(fn (array $layout) => [
        'handle' => $layout['handle'],
        'name' => $layout['name'],
        'description' => $layout['description'] ?? null,
        'is_system' => $layout['is_system'] ?? false,
        'is_active' => $layout['is_active'] ?? true,
        'sort_order' => $layout['sort_order'] ?? 0,
        'shell_type' => $layout['shell_type'] ?? 'default',
        'slot_schema' => isset($layout['slot_schema']) ? json_encode($layout['slot_schema'], JSON_UNESCAPED_SLASHES) : null,
        'wrapper_schema' => isset($layout['wrapper_schema']) ? json_encode($layout['wrapper_schema'], JSON_UNESCAPED_SLASHES) : null,
        'created_at' => $now,
        'updated_at' => $now,
      ])
      ->all();

    DB::table('page_layouts')->upsert(
      $rows,
      ['handle'],
      ['name', 'description', 'is_system', 'is_active', 'sort_order', 'shell_type', 'slot_schema', 'wrapper_schema', 'updated_at']
    );
  }

  public function down(): void
  {
    Schema::dropIfExists('page_layouts');
  }
};
