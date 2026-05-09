<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_variables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('label')->nullable();
            $table->text('value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['site_id', 'key']);
            $table->index(['site_id', 'sort_order', 'id']);
            $table->index(['site_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_variables');
    }
};
