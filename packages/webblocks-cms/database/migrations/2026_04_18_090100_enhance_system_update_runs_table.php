<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_update_runs')) {
            return;
        }

        Schema::table('system_update_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('system_update_runs', 'summary')) {
                $table->string('summary')->nullable()->after('status');
            }

            if (! Schema::hasColumn('system_update_runs', 'warning_count')) {
                $table->unsignedInteger('warning_count')->default(0)->after('output');
            }

            if (! Schema::hasColumn('system_update_runs', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('warning_count');
            }

            if (! Schema::hasColumn('system_update_runs', 'finished_at')) {
                $table->timestamp('finished_at')->nullable()->after('started_at');
            }

            if (! Schema::hasColumn('system_update_runs', 'duration_ms')) {
                $table->unsignedBigInteger('duration_ms')->nullable()->after('finished_at');
            }

            if (! Schema::hasColumn('system_update_runs', 'triggered_by_user_id')) {
                $table->foreignId('triggered_by_user_id')->nullable()->after('duration_ms')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_update_runs')) {
            return;
        }

        Schema::table('system_update_runs', function (Blueprint $table) {
            if (Schema::hasColumn('system_update_runs', 'triggered_by_user_id')) {
                $table->dropConstrainedForeignId('triggered_by_user_id');
            }

            $columns = collect(['summary', 'warning_count', 'started_at', 'finished_at', 'duration_ms'])
                ->filter(fn (string $column) => Schema::hasColumn('system_update_runs', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
