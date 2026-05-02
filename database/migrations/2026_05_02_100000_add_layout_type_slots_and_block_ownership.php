<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('layout_types', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('status');
        });

        Schema::create('layout_type_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('layout_type_id')->constrained('layout_types')->cascadeOnDelete();
            $table->foreignId('slot_type_id')->constrained('slot_types')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('ownership')->default('page');
            $table->string('wrapper_element')->nullable();
            $table->string('wrapper_preset')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['layout_type_id', 'sort_order']);
            $table->unique(['layout_type_id', 'slot_type_id']);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('layout_type_id')->nullable()->after('layout_id')->constrained('layout_types')->nullOnDelete();
        });

        Schema::table('blocks', function (Blueprint $table) {
            $table->foreignId('layout_type_slot_id')->nullable()->after('page_id')->constrained('layout_type_slots')->cascadeOnDelete();
            $table->foreignId('page_id')->nullable()->change();
            $table->index(['layout_type_slot_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->dropIndex(['layout_type_slot_id', 'sort_order']);
            $table->dropConstrainedForeignId('layout_type_slot_id');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('layout_type_id');
        });

        Schema::dropIfExists('layout_type_slots');

        Schema::table('layout_types', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
