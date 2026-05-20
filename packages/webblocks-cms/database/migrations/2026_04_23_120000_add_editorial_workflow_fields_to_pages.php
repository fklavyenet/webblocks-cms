<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            if (! Schema::hasColumn('pages', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('pages', 'review_requested_at')) {
                $table->timestamp('review_requested_at')->nullable()->after('published_at');
            }
        });

        DB::table('pages')
            ->whereNull('status')
            ->update(['status' => 'draft']);

        DB::table('pages')
            ->where('status', '')
            ->update(['status' => 'draft']);

        DB::table('pages')
            ->whereNotIn('status', ['draft', 'in_review', 'published', 'archived'])
            ->update(['status' => 'draft']);

        DB::table('pages')
            ->where('status', 'published')
            ->whereNull('published_at')
            ->update([
                'published_at' => DB::raw('coalesce(updated_at, created_at, current_timestamp)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            if (Schema::hasColumn('pages', 'review_requested_at')) {
                $table->dropColumn('review_requested_at');
            }

            if (Schema::hasColumn('pages', 'published_at')) {
                $table->dropColumn('published_at');
            }
        });

        DB::table('pages')
            ->whereIn('status', ['in_review', 'archived'])
            ->update(['status' => 'draft']);
    }
};
