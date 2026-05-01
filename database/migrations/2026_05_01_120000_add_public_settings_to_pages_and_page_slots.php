<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            if (! Schema::hasColumn('pages', 'settings')) {
                $table->json('settings')->nullable()->after('status');
            }
        });

        Schema::table('page_slots', function (Blueprint $table) {
            if (! Schema::hasColumn('page_slots', 'settings')) {
                $table->json('settings')->nullable()->after('sort_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('page_slots', function (Blueprint $table) {
            if (Schema::hasColumn('page_slots', 'settings')) {
                $table->dropColumn('settings');
            }
        });

        Schema::table('pages', function (Blueprint $table) {
            if (Schema::hasColumn('pages', 'settings')) {
                $table->dropColumn('settings');
            }
        });
    }
};
