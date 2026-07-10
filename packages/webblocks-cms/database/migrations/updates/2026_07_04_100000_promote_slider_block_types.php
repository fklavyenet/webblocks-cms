<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_block_types')) {
      return;
    }

    foreach ($this->definitions() as $definition) {
      $this->syncDefinition($definition);
    }
  }

  public function down(): void
  {
    // Catalog repair migrations are intentionally not destructive on rollback.
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function definitions(): array
  {
    return [
      [
        'name' => 'Slider',
        'slug' => 'slider',
        'category' => 'layout',
        'description' => 'Composable responsive slider container that fills its parent and owns slide children.',
        'source_type' => 'static',
        'is_system' => false,
        'is_container' => true,
        'sort_order' => 3,
        'status' => 'published',
      ],
      [
        'name' => 'Slide',
        'slug' => 'slide',
        'category' => 'layout',
        'description' => 'Slider child container with optional background media and nested content blocks.',
        'source_type' => 'static',
        'is_system' => false,
        'is_container' => true,
        'sort_order' => 4,
        'status' => 'published',
      ],
    ];
  }

  /**
   * @param  array<string, mixed>  $definition
   * @return array<string, mixed>
   */
  private function syncDefinition(array $definition): void
  {
    $exists = DB::table('wbcms_block_types')
      ->where('slug', $definition['slug'])
      ->exists();

    if ($exists) {
      if (Schema::hasColumn('wbcms_block_types', 'updated_at')) {
        $definition['updated_at'] = now();
      }

      DB::table('wbcms_block_types')
        ->where('slug', $definition['slug'])
        ->update($definition);

      return;
    }

    $now = now();

    if (Schema::hasColumn('wbcms_block_types', 'created_at')) {
      $definition['created_at'] = $now;
    }

    if (Schema::hasColumn('wbcms_block_types', 'updated_at')) {
      $definition['updated_at'] = $now;
    }

    DB::table('wbcms_block_types')->insert($definition);
  }
};
