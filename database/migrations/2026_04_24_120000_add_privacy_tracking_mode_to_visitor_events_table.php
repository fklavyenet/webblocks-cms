<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visitor_events')) {
            return;
        }

        Schema::table('visitor_events', function (Blueprint $table) {
            if (! Schema::hasColumn('visitor_events', 'tracking_mode')) {
                $table->string('tracking_mode', 16)->default('full')->after('path');
                $table->index(['tracking_mode', 'visited_at']);
            }
        });

        DB::table('visitor_events')->update(['tracking_mode' => 'full']);

        Schema::table('visitor_events', function (Blueprint $table) {
            $table->string('session_key', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('visitor_events')) {
            return;
        }

        DB::table('visitor_events')->whereNull('session_key')->update(['session_key' => '']);

        Schema::table('visitor_events', function (Blueprint $table) {
            $table->string('session_key', 64)->nullable(false)->change();

            if (Schema::hasColumn('visitor_events', 'tracking_mode')) {
                $table->dropIndex(['tracking_mode', 'visited_at']);
                $table->dropColumn('tracking_mode');
            }
        });
    }
};
