<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('block_types')
            ->where('slug', 'rich-text')
            ->update([
                'name' => 'Rich Text',
                'category' => 'content',
                'description' => 'Translated body copy with safe inline code formatting for editorial content.',
                'source_type' => 'static',
                'is_system' => false,
                'is_container' => false,
                'sort_order' => 6,
                'status' => 'published',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('block_types')
            ->where('slug', 'rich-text')
            ->update([
                'updated_at' => now(),
            ]);
    }
};
