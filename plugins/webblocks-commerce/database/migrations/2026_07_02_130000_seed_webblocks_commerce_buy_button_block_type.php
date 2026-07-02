<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('block_types')) {
      return;
    }

    DB::table('block_types')->updateOrInsert(
      ['slug' => 'webblocks-commerce-buy-button'],
      [
        'name' => 'Commerce Buy Button',
        'description' => 'Links visitors to a WebBlocks Commerce product buy page.',
        'category' => 'commerce',
        'source_type' => 'static',
        'is_system' => false,
        'is_container' => false,
        'sort_order' => 65,
        'status' => 'published',
        'created_at' => now(),
        'updated_at' => now(),
      ],
    );
  }

  public function down(): void
  {
    if (! Schema::hasTable('block_types')) {
      return;
    }

    $blockTypeId = DB::table('block_types')->where('slug', 'webblocks-commerce-buy-button')->value('id');

    if ($blockTypeId === null) {
      return;
    }

    if (Schema::hasTable('blocks') && DB::table('blocks')->where('block_type_id', $blockTypeId)->exists()) {
      return;
    }

    DB::table('block_types')->where('id', $blockTypeId)->delete();
  }
};
