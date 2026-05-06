<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_slots', function (Blueprint $table) {
            if (! Schema::hasColumn('shared_slots', 'created_by_user_id')) {
                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->after('is_active')
                    ->constrained('users', indexName: 'ss_created_by_fk')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('shared_slots', 'updated_by_user_id')) {
                $table->foreignId('updated_by_user_id')
                    ->nullable()
                    ->after('created_by_user_id')
                    ->constrained('users', indexName: 'ss_updated_by_fk')
                    ->nullOnDelete();
            }
        });

        Schema::table('shared_slot_revisions', function (Blueprint $table) {
            if (! Schema::hasColumn('shared_slot_revisions', 'created_by_user_id')) {
                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users', indexName: 'ssr_created_by_fk')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('shared_slot_revisions', 'source')) {
                $table->string('source', 50)->nullable()->after('created_by_user_id');
            }

            if (! Schema::hasColumn('shared_slot_revisions', 'event')) {
                $table->string('event', 100)->nullable()->after('source');
            }
        });

        DB::table('shared_slot_revisions')
            ->whereNull('created_by_user_id')
            ->whereNotNull('user_id')
            ->update(['created_by_user_id' => DB::raw('user_id')]);

        DB::table('shared_slot_revisions')
            ->whereNull('event')
            ->whereNotNull('source_event')
            ->update(['event' => DB::raw('source_event')]);

        DB::table('shared_slot_revisions')
            ->whereNull('source')
            ->whereNotNull('created_by_user_id')
            ->update(['source' => 'admin']);

        DB::table('shared_slot_revisions')
            ->whereNull('source')
            ->update(['source' => 'system']);
    }

    public function down(): void
    {
        Schema::table('shared_slot_revisions', function (Blueprint $table) {
            if (Schema::hasColumn('shared_slot_revisions', 'event')) {
                $table->dropColumn('event');
            }

            if (Schema::hasColumn('shared_slot_revisions', 'source')) {
                $table->dropColumn('source');
            }

            if (Schema::hasColumn('shared_slot_revisions', 'created_by_user_id')) {
                $table->dropConstrainedForeignId('created_by_user_id');
            }
        });

        Schema::table('shared_slots', function (Blueprint $table) {
            if (Schema::hasColumn('shared_slots', 'updated_by_user_id')) {
                $table->dropConstrainedForeignId('updated_by_user_id');
            }

            if (Schema::hasColumn('shared_slots', 'created_by_user_id')) {
                $table->dropConstrainedForeignId('created_by_user_id');
            }
        });
    }
};
