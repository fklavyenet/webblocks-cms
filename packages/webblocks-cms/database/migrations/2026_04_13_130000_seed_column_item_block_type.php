<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('block_types')->updateOrInsert(
            ['slug' => 'column_item'],
            [
                'name' => 'Column Item',
                'description' => 'Add one card or column entry inside a Columns container.',
                'category' => 'layout helper',
                'source_type' => 'static',
                'is_system' => true,
                'is_container' => false,
                'sort_order' => 50,
                'status' => 'published',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('block_types')->where('slug', 'column_item')->delete();
    }
};
