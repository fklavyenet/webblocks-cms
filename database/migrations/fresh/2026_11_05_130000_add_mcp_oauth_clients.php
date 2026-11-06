<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_mcp_oauth_clients')) {
      Schema::create('wbcms_mcp_oauth_clients', function (Blueprint $table): void {
        $table->string('id', 64)->primary();
        $table->string('name', 128)->nullable();
        $table->timestamp('created_at');
      });
    }

    if (! Schema::hasTable('wbcms_mcp_oauth_redirects')) {
      Schema::create('wbcms_mcp_oauth_redirects', function (Blueprint $table): void {
        $table->id();
        $table->string('client_id', 64);
        $table->foreign('client_id')->references('id')->on('wbcms_mcp_oauth_clients')->cascadeOnDelete();
        $table->text('uri');
      });
    }

    if (! Schema::hasColumn('wbcms_mcp_grants', 'oauth_client_id')) {
      Schema::table('wbcms_mcp_grants', function (Blueprint $table): void {
        $table->string('oauth_client_id', 64)->nullable();
        $table->foreign('oauth_client_id')->references('id')->on('wbcms_mcp_oauth_clients')->cascadeOnDelete();
        $table->char('redirect_hash', 64)->nullable();
        $table->index(['user_id', 'oauth_client_id']);
      });
    }
  }

  public function down(): void
  {
    Schema::table('wbcms_mcp_grants', function (Blueprint $table): void {
      $table->dropForeign(['oauth_client_id']);
      $table->dropIndex(['user_id', 'oauth_client_id']);
    });
    Schema::table('wbcms_mcp_grants', function (Blueprint $table): void {
      $table->dropColumn(['oauth_client_id', 'redirect_hash']);
    });
    Schema::dropIfExists('wbcms_mcp_oauth_redirects');
    Schema::dropIfExists('wbcms_mcp_oauth_clients');
  }
};
