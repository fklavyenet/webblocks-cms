<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            ['slug' => 'section', 'name' => 'Section', 'category' => 'layout', 'sort_order' => 10, 'description' => 'Create a page section that can hold headings, text, media, and calls to action.'],
            ['slug' => 'heading', 'name' => 'Hero', 'category' => 'marketing', 'sort_order' => 11, 'description' => 'Add a strong title or hero headline to introduce a section or page.'],
            ['slug' => 'rich-text', 'name' => 'Rich Text', 'category' => 'content', 'sort_order' => 12, 'description' => 'Write formatted body content for stories, summaries, and page copy.'],
            ['slug' => 'callout', 'name' => 'CTA', 'category' => 'marketing', 'sort_order' => 13, 'description' => 'Highlight a key next step or promotional call to action.'],
            ['slug' => 'gallery', 'name' => 'Features', 'category' => 'media', 'sort_order' => 14, 'description' => 'Show a small visual grid for features, values, or image highlights.'],
        ] as $item) {
            DB::table('block_types')->updateOrInsert(
                ['slug' => $item['slug']],
                [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'category' => $item['category'],
                    'source_type' => 'static',
                    'status' => 'published',
                    'updated_at' => $now,
                    'created_at' => $now,
                ] + ['sort_order' => $item['sort_order']]
            );
        }
    }

    public function down(): void
    {
        foreach (['section', 'heading', 'rich-text', 'callout', 'gallery'] as $slug) {
            DB::table('block_types')->where('slug', $slug)->update([
                'updated_at' => now(),
            ]);
        }
    }
};
