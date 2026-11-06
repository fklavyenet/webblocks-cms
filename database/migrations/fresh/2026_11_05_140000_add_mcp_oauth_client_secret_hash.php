<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasColumn('wbcms_mcp_oauth_clients', 'client_secret_hash')) {
      Schema::table('wbcms_mcp_oauth_clients', function (Blueprint $table): void {
        $table->char('client_secret_hash', 64)->nullable();
      });
    }
  }

  public function down(): void
  {
    Schema::table('wbcms_mcp_oauth_clients', function (Blueprint $table): void {
      $table->dropColumn('client_secret_hash');
    });
  }
};
