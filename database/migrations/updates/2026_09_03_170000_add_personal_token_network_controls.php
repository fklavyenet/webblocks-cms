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
      if (! Schema::hasColumn('wbcms_cms_api_tokens', 'allowed_ip_ranges')) {
        $table->json('allowed_ip_ranges')->nullable()->after('expires_at');
      }

      if (! Schema::hasColumn('wbcms_cms_api_tokens', 'requests_per_minute')) {
        $table->unsignedSmallInteger('requests_per_minute')->nullable()->after('allowed_ip_ranges');
      }
    });
  }

  public function down(): void
  {
    // Existing-install repair migrations are intentionally non-destructive.
  }
};
