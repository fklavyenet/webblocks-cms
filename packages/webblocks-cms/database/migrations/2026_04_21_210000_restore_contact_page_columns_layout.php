<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $pageId = DB::table('page_translations')
                ->where('path', '/p/contact')
                ->value('page_id');

            if (! $pageId) {
                return;
            }

            $contactInfo = DB::table('blocks')
                ->where('page_id', $pageId)
                ->where('type', 'contact-info')
                ->first();

            $contactForm = DB::table('blocks')
                ->where('page_id', $pageId)
                ->where('type', 'contact_form')
                ->first();

            if (! $contactInfo || ! $contactForm) {
                return;
            }

            $columnsType = DB::table('block_types')
                ->where('slug', 'columns')
                ->first();

            if (! $columnsType) {
                return;
            }

            $mainSlotTypeId = $contactInfo->slot_type_id ?: $contactForm->slot_type_id;
            $columnsSortOrder = min((int) $contactInfo->sort_order, (int) $contactForm->sort_order);

            $existingColumns = DB::table('blocks')
                ->where('page_id', $pageId)
                ->where('type', 'columns')
                ->whereNull('parent_id')
                ->where('slot', 'main')
                ->orderBy('id')
                ->first();

            $now = now();

            if ($existingColumns) {
                $columnsId = $existingColumns->id;

                DB::table('blocks')
                    ->where('id', $columnsId)
                    ->update([
                        'slot_type_id' => $mainSlotTypeId,
                        'slot' => 'main',
                        'sort_order' => $columnsSortOrder,
                        'status' => 'published',
                        'updated_at' => $now,
                    ]);
            } else {
                $columnsId = DB::table('blocks')->insertGetId([
                    'page_id' => $pageId,
                    'parent_id' => null,
                    'type' => 'columns',
                    'block_type_id' => $columnsType->id,
                    'source_type' => $columnsType->source_type ?: 'static',
                    'slot' => 'main',
                    'slot_type_id' => $mainSlotTypeId,
                    'sort_order' => $columnsSortOrder,
                    'title' => null,
                    'subtitle' => null,
                    'content' => null,
                    'url' => null,
                    'asset_id' => null,
                    'variant' => null,
                    'meta' => null,
                    'settings' => null,
                    'status' => 'published',
                    'is_system' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('blocks')
                ->where('id', $contactInfo->id)
                ->update([
                    'parent_id' => $columnsId,
                    'slot_type_id' => $mainSlotTypeId,
                    'slot' => 'main',
                    'sort_order' => 0,
                    'updated_at' => $now,
                ]);

            DB::table('blocks')
                ->where('id', $contactForm->id)
                ->update([
                    'parent_id' => $columnsId,
                    'slot_type_id' => $mainSlotTypeId,
                    'slot' => 'main',
                    'sort_order' => 1,
                    'updated_at' => $now,
                ]);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $pageId = DB::table('page_translations')
                ->where('path', '/p/contact')
                ->value('page_id');

            if (! $pageId) {
                return;
            }

            $columns = DB::table('blocks')
                ->where('page_id', $pageId)
                ->where('type', 'columns')
                ->whereNull('parent_id')
                ->where('slot', 'main')
                ->orderBy('id')
                ->first();

            if (! $columns) {
                return;
            }

            $now = now();

            DB::table('blocks')
                ->where('page_id', $pageId)
                ->where('parent_id', $columns->id)
                ->where('type', 'contact-info')
                ->update([
                    'parent_id' => null,
                    'sort_order' => 1,
                    'updated_at' => $now,
                ]);

            DB::table('blocks')
                ->where('page_id', $pageId)
                ->where('parent_id', $columns->id)
                ->where('type', 'contact_form')
                ->update([
                    'parent_id' => null,
                    'sort_order' => 2,
                    'updated_at' => $now,
                ]);

            DB::table('blocks')
                ->where('id', $columns->id)
                ->delete();
        });
    }
};
