<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('cms_api_tokens', function (Blueprint $table): void {
      $table->id();
      $table->string('name');
      $table->string('token_hash', 128)->unique();
      $table->string('token_preview', 32);
      $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamp('last_used_at')->nullable();
      $table->string('last_used_ip', 45)->nullable();
      $table->timestamp('revoked_at')->nullable();
      $table->timestamps();

      $table->index(['revoked_at', 'created_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cms_api_tokens');
  }
};
