<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('block_types', 'is_recommended')) {
            return;
        }

        Schema::table('block_types', function (Blueprint $table) {
            $table->dropColumn('is_recommended');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('block_types', 'is_recommended')) {
            return;
        }

        Schema::table('block_types', function (Blueprint $table) {
            $table->boolean('is_recommended')->default(false)->after('is_container');
        });
    }
};
