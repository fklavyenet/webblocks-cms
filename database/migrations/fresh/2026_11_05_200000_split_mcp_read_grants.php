<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  private const READ_COLUMNS = [
    'can_read_content' => 'can_edit_content',
    'can_read_structure' => 'can_edit_structure',
    'can_read_translations' => 'can_edit_translations',
    'can_read_media' => 'can_edit_content',
  ];

  public function up(): void
  {
    foreach (self::READ_COLUMNS as $read => $previous) {
      if (! Schema::hasColumn('wbcms_mcp_grants', $read)) {
        Schema::table('wbcms_mcp_grants', fn (Blueprint $table) => $table->boolean($read)->default(false));
        DB::table('wbcms_mcp_grants')->where($previous, true)->update([$read => true]);
      }
    }
  }

  public function down(): void
  {
    // The older server would infer these reads from write grants. Reauthorize
    // rather than letting a rollback widen the permissions of an existing token.
    DB::table('wbcms_mcp_grants')->delete();
    Schema::table('wbcms_mcp_grants', fn (Blueprint $table) => $table->dropColumn(array_keys(self::READ_COLUMNS)));
  }
};
