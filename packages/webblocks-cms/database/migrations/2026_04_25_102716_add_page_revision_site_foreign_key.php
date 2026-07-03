<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\Page;

return new class extends Migration
{
  /**
     * Run the migrations.
     */
  public function up(): void
  {
    Page::query()
      ->select(['id', 'site_id'])
      ->orderBy('id')
      ->get()
      ->each(fn (Page $page) => DB::table('wbcms_page_revisions')
        ->where('page_id', $page->id)
        ->update(['site_id' => $page->site_id]));

    Schema::table('wbcms_page_revisions', function (Blueprint $table) {
      $table->foreign('site_id')->references('id')->on('wbcms_sites')->restrictOnDelete();
    });
  }

  /**
     * Reverse the migrations.
     */
  public function down(): void
  {
    Schema::table('wbcms_page_revisions', function (Blueprint $table) {
      $table->dropForeign(['site_id']);
    });
  }
};
