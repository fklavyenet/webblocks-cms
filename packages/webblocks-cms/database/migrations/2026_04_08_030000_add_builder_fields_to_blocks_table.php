<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->string('subtitle')->nullable()->after('title');
            $table->string('url')->nullable()->after('content');
            $table->string('variant')->nullable()->after('url');
            $table->text('meta')->nullable()->after('variant');
        });
    }

    public function down(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->dropColumn(['subtitle', 'url', 'variant', 'meta']);
        });
    }
};
