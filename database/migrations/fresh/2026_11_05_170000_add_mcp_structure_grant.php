<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasColumn('wbcms_mcp_grants', 'can_edit_structure')) {
      Schema::table('wbcms_mcp_grants', function (Blueprint $table): void {
        $table->boolean('can_edit_structure')->default(false);
      });
    }
  }

  public function down(): void
  {
    Schema::table('wbcms_mcp_grants', function (Blueprint $table): void {
      $table->dropColumn('can_edit_structure');
    });
  }
};
