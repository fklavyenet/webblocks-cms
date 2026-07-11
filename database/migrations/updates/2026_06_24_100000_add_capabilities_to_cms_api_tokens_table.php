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
      if (! Schema::hasColumn('wbcms_cms_api_tokens', 'capabilities')) {
        $table->json('capabilities')->nullable()->after('token_preview');
      }

      if (! Schema::hasColumn('wbcms_cms_api_tokens', 'last_used_user_agent')) {
        $table->string('last_used_user_agent', 255)->nullable()->after('last_used_ip');
      }
    });
  }

  public function down(): void
  {
    if (! Schema::hasTable('wbcms_cms_api_tokens')) {
      return;
    }

    Schema::table('wbcms_cms_api_tokens', function (Blueprint $table): void {
      if (Schema::hasColumn('wbcms_cms_api_tokens', 'last_used_user_agent')) {
        $table->dropColumn('last_used_user_agent');
      }

      if (Schema::hasColumn('wbcms_cms_api_tokens', 'capabilities')) {
        $table->dropColumn('capabilities');
      }
    });
  }
};
