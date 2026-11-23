<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('wbcms_plugin_lifecycles') && ! Schema::hasColumn('wbcms_plugin_lifecycles', 'failure_reference')) {
      Schema::table('wbcms_plugin_lifecycles', function (Blueprint $table): void {
        $table->uuid('failure_reference')->nullable();
      });
    }
  }

  public function down(): void
  {
    if (Schema::hasColumn('wbcms_plugin_lifecycles', 'failure_reference')) {
      Schema::table('wbcms_plugin_lifecycles', function (Blueprint $table): void {
        $table->dropColumn('failure_reference');
      });
    }
  }
};
