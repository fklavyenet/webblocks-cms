<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('block_types')
            ->where('slug', 'card')
            ->update(['is_container' => true]);
    }

    public function down(): void
    {
        DB::table('block_types')
            ->where('slug', 'card')
            ->update(['is_container' => false]);
    }
};
