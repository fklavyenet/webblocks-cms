<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Page::query()
            ->select(['id', 'site_id'])
            ->orderBy('id')
            ->get()
            ->each(fn (Page $page) => DB::table('page_revisions')
                ->where('page_id', $page->id)
                ->update(['site_id' => $page->site_id]));

        Schema::table('page_revisions', function (Blueprint $table) {
            $table->foreign('site_id')->references('id')->on('sites')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('page_revisions', function (Blueprint $table) {
            $table->dropForeign(['site_id']);
        });
    }
};
