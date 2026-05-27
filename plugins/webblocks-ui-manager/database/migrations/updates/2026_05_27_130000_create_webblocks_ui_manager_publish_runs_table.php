<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('webblocks_ui_manager_releases') || Schema::hasTable('webblocks_ui_manager_publish_runs')) {
      return;
    }

    Schema::create('webblocks_ui_manager_publish_runs', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('release_id')->constrained('webblocks_ui_manager_releases')->cascadeOnDelete();
      $table->string('mode', 16);
      $table->string('status', 32);
      $table->string('target_root');
      $table->string('target_release_path');
      $table->json('operations')->nullable();
      $table->text('message')->nullable();
      $table->timestamp('started_at')->nullable();
      $table->timestamp('finished_at')->nullable();
      $table->timestamps();

      $table->index(['release_id', 'created_at']);
      $table->index(['status', 'created_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('webblocks_ui_manager_publish_runs');
  }
};
