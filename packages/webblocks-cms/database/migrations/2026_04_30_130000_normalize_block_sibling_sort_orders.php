<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $groups = DB::table('blocks')
            ->select('page_id', 'slot_type_id', 'parent_id')
            ->groupBy('page_id', 'slot_type_id', 'parent_id')
            ->get();

        foreach ($groups as $group) {
            $siblings = DB::table('blocks')
                ->where('page_id', $group->page_id)
                ->where('slot_type_id', $group->slot_type_id)
                ->where(function ($query) use ($group) {
                    if ($group->parent_id === null) {
                        $query->whereNull('parent_id');

                        return;
                    }

                    $query->where('parent_id', $group->parent_id);
                })
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'sort_order']);

            foreach ($siblings as $index => $sibling) {
                if ((int) $sibling->sort_order === $index) {
                    continue;
                }

                DB::table('blocks')
                    ->where('id', $sibling->id)
                    ->update([
                        'sort_order' => $index,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Irreversible normalization.
    }
};
