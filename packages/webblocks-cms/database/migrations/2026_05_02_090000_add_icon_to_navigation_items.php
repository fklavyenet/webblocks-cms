<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            if (! Schema::hasColumn('navigation_items', 'icon')) {
                $table->string('icon')->nullable()->after('target');
            }
        });
    }

    public function down(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            if (Schema::hasColumn('navigation_items', 'icon')) {
                $table->dropColumn('icon');
            }
        });
    }
};
