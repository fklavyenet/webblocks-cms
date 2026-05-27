<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('webblocks_ui_manager_releases', function (Blueprint $table): void {
      $table->id();
      $table->string('version')->unique();
      $table->string('label')->nullable();
      $table->string('status', 32)->default('draft');
      $table->text('notes')->nullable();
      $table->string('cdn_base_path')->nullable();
      $table->string('cdn_base_url')->nullable();
      $table->string('manifest_path')->nullable();
      $table->json('manifest')->nullable();
      $table->timestamp('prepared_at')->nullable();
      $table->timestamp('published_at')->nullable();
      $table->timestamps();

      $table->index(['status', 'created_at']);
    });

    Schema::create('webblocks_ui_manager_artifacts', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('release_id')->constrained('webblocks_ui_manager_releases')->cascadeOnDelete();
      $table->string('handle');
      $table->string('source_path')->nullable();
      $table->string('target_path');
      $table->string('public_url')->nullable();
      $table->string('checksum_sha256', 64);
      $table->unsignedBigInteger('size_bytes')->nullable();
      $table->string('mime_type')->nullable();
      $table->json('metadata')->nullable();
      $table->string('status', 32)->default('tracked');
      $table->timestamps();

      $table->unique(['release_id', 'handle']);
      $table->index(['status', 'created_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('webblocks_ui_manager_artifacts');
    Schema::dropIfExists('webblocks_ui_manager_releases');
  }
};
