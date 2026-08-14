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
      $exists = DB::table('wbcms_block_types')
        ->where('slug', $definition['slug'])
        ->exists();

      $timestamps = [];

      if (Schema::hasColumn('wbcms_block_types', 'updated_at')) {
        $timestamps['updated_at'] = now();
      }

      if ($exists) {
        DB::table('wbcms_block_types')
          ->where('slug', $definition['slug'])
          ->update($definition + $timestamps);

        continue;
      }

      if (Schema::hasColumn('wbcms_block_types', 'created_at')) {
        $timestamps['created_at'] = now();
      }

      DB::table('wbcms_block_types')->insert($definition + $timestamps);
    }
  }

  public function down(): void
  {
    // Catalog promotion is intentionally not destructive on rollback.
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function definitions(): array
  {
    return [
      [
        'name' => 'Stack',
        'slug' => 'stack',
        'category' => 'layout',
        'description' => 'Stacks child blocks vertically and controls the space between them.',
        'source_type' => 'static',
        'is_system' => false,
        'is_container' => true,
        'sort_order' => 4,
        'status' => 'published',
      ],
      [
        'name' => 'Split',
        'slug' => 'split',
        'category' => 'layout',
        'description' => 'Two-sided layout whose first child grows while the second stays content-sized.',
        'source_type' => 'static',
        'is_system' => false,
        'is_container' => true,
        'sort_order' => 4,
        'status' => 'published',
      ],
    ];
  }
};
