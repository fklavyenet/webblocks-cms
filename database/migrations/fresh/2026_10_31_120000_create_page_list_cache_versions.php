<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use WebBlocks\Cms\Support\Pages\PageListCache;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable(PageListCache::TABLE)) {
      Schema::create(PageListCache::TABLE, function (Blueprint $table): void {
        $table->unsignedTinyInteger('id')->primary();
        $table->uuid('generation');
      });
    }
    DB::table(PageListCache::TABLE)->insertOrIgnore(['id' => 1, 'generation' => (string) Str::uuid()]);
  }

  public function down(): void
  {
    Schema::dropIfExists(PageListCache::TABLE);
  }
};
