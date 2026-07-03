<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('wbcms_cms_api_token_activity_logs')) {
      return;
    }

    if (! Schema::hasTable('wbcms_cms_api_tokens')) {
      return;
    }

    Schema::create('wbcms_cms_api_token_activity_logs', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('cms_api_token_id')->constrained('wbcms_cms_api_tokens')->cascadeOnDelete();
      $table->timestamp('occurred_at')->useCurrent();
      $table->string('status', 32);
      $table->string('method', 12);
      $table->string('path', 512);
      $table->string('route_name')->nullable();
      $table->string('required_capability')->nullable();
      $table->string('ip', 45)->nullable();
      $table->string('user_agent', 255)->nullable();
      $table->timestamps();

      $table->index(['cms_api_token_id', 'occurred_at'], 'wbcms_api_token_activity_token_time_idx');
    });
  }

  public function down(): void
  {
    // Existing-install repair migrations are intentionally not destructive on rollback.
  }
};
