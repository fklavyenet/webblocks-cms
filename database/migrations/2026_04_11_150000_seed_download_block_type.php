<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('block_types')->updateOrInsert(
            ['slug' => 'download'],
            [
                'name' => 'Download',
                'description' => 'Document or file download block with internal asset support.',
                'category' => 'media',
                'source_type' => 'static',
                'is_system' => false,
                'is_container' => false,
                'sort_order' => 40,
                'status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('block_types')->where('slug', 'download')->delete();
    }
};
