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
            if (! Schema::hasColumn('pages', 'created_by_user_id')) {
                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->after('review_requested_at')
                    ->constrained('users', indexName: 'pg_created_by_fk')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('pages', 'updated_by_user_id')) {
                $table->foreignId('updated_by_user_id')
                    ->nullable()
                    ->after('created_by_user_id')
                    ->constrained('users', indexName: 'pg_updated_by_fk')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('pages', 'published_by_user_id')) {
                $table->foreignId('published_by_user_id')
                    ->nullable()
                    ->after('updated_by_user_id')
                    ->constrained('users', indexName: 'pg_published_by_fk')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('pages', 'archived_by_user_id')) {
                $table->foreignId('archived_by_user_id')
                    ->nullable()
                    ->after('published_by_user_id')
                    ->constrained('users', indexName: 'pg_archived_by_fk')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('pages', 'review_requested_by_user_id')) {
                $table->foreignId('review_requested_by_user_id')
                    ->nullable()
                    ->after('archived_by_user_id')
                    ->constrained('users', indexName: 'pg_review_by_fk')
                    ->nullOnDelete();
            }
        });

        Schema::table('page_revisions', function (Blueprint $table) {
            if (! Schema::hasColumn('page_revisions', 'created_by_user_id')) {
                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('users', indexName: 'pr_created_by_fk')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('page_revisions', 'source')) {
                $table->string('source', 50)->nullable()->after('created_by_user_id');
            }

            if (! Schema::hasColumn('page_revisions', 'event')) {
                $table->string('event', 100)->nullable()->after('source');
            }
        });

        DB::table('page_revisions')
            ->whereNull('created_by_user_id')
            ->whereNotNull('created_by')
            ->update(['created_by_user_id' => DB::raw('created_by')]);

        DB::table('page_revisions')
            ->whereNull('source')
            ->whereNotNull('created_by_user_id')
            ->update(['source' => 'admin']);

        DB::table('page_revisions')
            ->whereNull('source')
            ->update(['source' => 'system']);
    }

    public function down(): void
    {
        Schema::table('page_revisions', function (Blueprint $table) {
            if (Schema::hasColumn('page_revisions', 'event')) {
                $table->dropColumn('event');
            }

            if (Schema::hasColumn('page_revisions', 'source')) {
                $table->dropColumn('source');
            }

            if (Schema::hasColumn('page_revisions', 'created_by_user_id')) {
                $table->dropConstrainedForeignId('created_by_user_id');
            }
        });

        Schema::table('pages', function (Blueprint $table) {
            if (Schema::hasColumn('pages', 'review_requested_by_user_id')) {
                $table->dropConstrainedForeignId('review_requested_by_user_id');
            }

            if (Schema::hasColumn('pages', 'archived_by_user_id')) {
                $table->dropConstrainedForeignId('archived_by_user_id');
            }

            if (Schema::hasColumn('pages', 'published_by_user_id')) {
                $table->dropConstrainedForeignId('published_by_user_id');
            }

            if (Schema::hasColumn('pages', 'updated_by_user_id')) {
                $table->dropConstrainedForeignId('updated_by_user_id');
            }

            if (Schema::hasColumn('pages', 'created_by_user_id')) {
                $table->dropConstrainedForeignId('created_by_user_id');
            }
        });
    }
};
