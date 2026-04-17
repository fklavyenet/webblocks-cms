<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->nullable()->constrained('asset_folders')->nullOnDelete();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('filename');
            $table->string('original_name');
            $table->string('extension')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('kind', 32);
            $table->string('visibility')->default('public');
            $table->string('title')->nullable();
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kind', 'created_at']);
            $table->index(['folder_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
