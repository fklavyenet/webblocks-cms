<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $timestamp = now();

        DB::table('block_types')->updateOrInsert(
            ['slug' => 'link-list'],
            [
                'name' => 'Link List',
                'description' => 'WebBlocks UI link list container for structured navigation rows.',
                'category' => 'navigation',
                'source_type' => 'static',
                'is_system' => false,
                'is_container' => true,
                'sort_order' => 11,
                'status' => 'published',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        );

        DB::table('block_types')->updateOrInsert(
            ['slug' => 'link-list-item'],
            [
                'name' => 'Link List Item',
                'description' => 'One row in a WebBlocks UI link list with title, meta, description, and URL.',
                'category' => 'navigation',
                'source_type' => 'static',
                'is_system' => false,
                'is_container' => false,
                'sort_order' => 12,
                'status' => 'published',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        );
    }

    public function down(): void
    {
        DB::table('block_types')
            ->whereIn('slug', ['link-list', 'link-list-item'])
            ->update([
                'category' => 'legacy',
                'is_container' => false,
                'sort_order' => 100,
                'status' => 'draft',
                'updated_at' => now(),
            ]);
    }
};
