<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_layouts', function (Blueprint $table) {
            $table->string('body_class')->nullable()->after('sort_order');
        });

        Schema::create('page_layout_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_layout_id')->constrained('page_layouts')->cascadeOnDelete();
            $table->foreignId('slot_type_id')->constrained('slot_types')->restrictOnDelete();
            $table->string('slot_name');
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->string('html_element')->default('div');
            $table->string('html_id')->nullable();
            $table->text('html_classes')->nullable();
            $table->longText('before_html')->nullable();
            $table->longText('start_html')->nullable();
            $table->longText('end_html')->nullable();
            $table->longText('after_html')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['page_layout_id', 'slot_name']);
            $table->index(['page_layout_id', 'is_active', 'sort_order']);
        });

        DB::table('page_layouts')
            ->where('handle', 'default')
            ->update(['body_class' => 'layout-default']);

        DB::table('page_layouts')
            ->where('handle', 'docs')
            ->update(['body_class' => 'layout-docs']);
    }

    public function down(): void
    {
        Schema::dropIfExists('page_layout_slots');

        Schema::table('page_layouts', function (Blueprint $table) {
            $table->dropColumn('body_class');
        });
    }
};
