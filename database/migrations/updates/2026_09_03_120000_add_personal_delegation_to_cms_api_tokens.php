<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_cms_api_tokens')) {
      return;
    }

    Schema::table('wbcms_cms_api_tokens', function (Blueprint $table): void {
      if (! Schema::hasColumn('wbcms_cms_api_tokens', 'token_type')) {
        $table->string('token_type', 20)->default('system')->after('token_preview');
      }

      if (! Schema::hasColumn('wbcms_cms_api_tokens', 'allowed_site_ids')) {
        $table->json('allowed_site_ids')->nullable()->after('capabilities');
      }

      if (! Schema::hasColumn('wbcms_cms_api_tokens', 'expires_at')) {
        $table->timestamp('expires_at')->nullable()->after('allowed_site_ids');
      }
    });
  }

  public function down(): void
  {
    // Existing-install repair migrations are intentionally non-destructive.
  }
};
