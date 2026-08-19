<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('wbcms_embedded_applications', function (Blueprint $table): void {
      $table->id();
      $table->string('handle', 64)->unique();
      $table->string('name');
      $table->text('description')->nullable();
      $table->string('version', 64)->default('1.0.0');
      $table->string('render_mode', 16);
      $table->string('entry_url', 2048)->nullable();
      $table->string('mount_element', 16)->nullable();
      $table->string('mount_classes', 512)->nullable();
      $table->json('css_assets')->nullable();
      $table->json('js_assets')->nullable();
      $table->json('supports')->nullable();
      $table->json('settings_schema')->nullable();
      $table->boolean('is_enabled')->default(true);
      $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamps();
      $table->index(['is_enabled', 'name']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_embedded_applications');
  }
};
