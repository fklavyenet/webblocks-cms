<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\EditorRecovery\RecoverySchema;

return new class extends Migration
{
  public function up(): void
  {
    RecoverySchema::extendForms();
  }

  public function down(): void
  {
    if (! Schema::hasColumn('wbcms_editor_recoveries', 'form_kind')) {
      return;
    }
    DB::table('wbcms_editor_recoveries')->whereNotNull('form_kind')->delete();
    Schema::table('wbcms_editor_recoveries', function (Blueprint $table): void {
      $table->dropColumn(['form_kind', 'block_type_id', 'slot_type_id', 'parent_block_id', 'translation_id', 'completed_page_id', 'completed_block_id', 'completed_translation_id']);
      $table->unsignedBigInteger('page_id')->nullable(false)->change();
    });
  }
};
