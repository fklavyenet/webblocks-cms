<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_staged_page_bases')) {
      Schema::create('wbcms_staged_page_bases', function (Blueprint $table): void {
        $table->foreignId('page_id')->primary()->constrained('wbcms_pages')->cascadeOnDelete();
        $table->char('source_fingerprint', 64);
      });
    }

    if (! Schema::hasTable('wbcms_mcp_grants')) {
      Schema::create('wbcms_mcp_grants', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
        $table->char('client_fingerprint', 64);
        $table->boolean('can_write');
        $table->char('code_hash', 64)->nullable()->unique();
        $table->char('code_challenge', 43)->nullable();
        $table->char('token_hash', 64)->nullable()->unique();
        $table->timestamp('expires_at')->index();
      });
    }
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_mcp_grants');
    Schema::dropIfExists('wbcms_staged_page_bases');
  }
};
