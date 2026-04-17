<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $columnItemType = DB::table('block_types')->where('slug', 'column_item')->first();

        if (! $columnItemType) {
            return;
        }

        DB::table('blocks')
            ->join('blocks as parents', 'parents.id', '=', 'blocks.parent_id')
            ->where('parents.type', 'columns')
            ->where('blocks.type', 'text')
            ->update([
                'blocks.block_type_id' => $columnItemType->id,
                'blocks.type' => 'column_item',
                'blocks.source_type' => $columnItemType->source_type ?? 'static',
                'blocks.updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $textType = DB::table('block_types')->where('slug', 'text')->first();

        if (! $textType) {
            return;
        }

        DB::table('blocks')
            ->join('blocks as parents', 'parents.id', '=', 'blocks.parent_id')
            ->where('parents.type', 'columns')
            ->where('blocks.type', 'column_item')
            ->update([
                'blocks.block_type_id' => $textType->id,
                'blocks.type' => 'text',
                'blocks.source_type' => $textType->source_type ?? 'static',
                'blocks.updated_at' => now(),
            ]);
    }
};
