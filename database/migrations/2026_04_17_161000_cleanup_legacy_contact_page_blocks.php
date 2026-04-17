<?php

use App\Models\Block;
use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $page = Page::query()->where('slug', 'contact')->first();

        if (! $page) {
            return;
        }

        Block::query()
            ->where('page_id', $page->id)
            ->whereIn('type', ['form', 'input', 'select', 'checkbox-group', 'radio-group', 'textarea', 'submit'])
            ->delete();

        Block::query()
            ->where('page_id', $page->id)
            ->where('type', 'page-title')
            ->update(['sort_order' => 0]);

        Block::query()
            ->where('page_id', $page->id)
            ->where('type', 'contact_form')
            ->update(['sort_order' => 1]);

        Block::query()
            ->where('page_id', $page->id)
            ->where('type', 'map')
            ->update(['sort_order' => 2]);

        Block::query()
            ->where('page_id', $page->id)
            ->where('type', 'social-links')
            ->update(['sort_order' => 3]);

        Block::query()
            ->where('page_id', $page->id)
            ->where('type', 'image')
            ->update(['sort_order' => 4]);
    }

    public function down(): void
    {
        // Legacy showcase form blocks are intentionally not recreated.
    }
};
