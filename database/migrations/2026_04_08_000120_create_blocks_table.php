<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('blocks')->nullOnDelete();
            $table->string('type');
            $table->string('source_type');
            $table->string('slot');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('title')->nullable();
            $table->longText('content')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
