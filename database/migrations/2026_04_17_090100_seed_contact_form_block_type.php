<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('block_types')->updateOrInsert(
            ['slug' => 'contact_form'],
            [
                'name' => 'Contact Form',
                'description' => 'Collect messages from a public page and send an editorial notification after saving the submission.',
                'category' => 'form',
                'source_type' => 'form',
                'is_system' => false,
                'is_container' => false,
                'sort_order' => 31,
                'status' => 'published',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('block_types')->where('slug', 'contact_form')->delete();
    }
};
