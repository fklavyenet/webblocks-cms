<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_media_folders')) {
      return;
    }

    if (! Schema::hasColumn('wbcms_media_folders', 'slug')) {
      Schema::table('wbcms_media_folders', function (Blueprint $table): void {
        $table->string('slug')->nullable()->after('name');
      });
    }

    $this->backfillMissingSlugs();
  }

  public function down(): void
  {
    // Repair migrations are intentionally not destructive on rollback.
  }

  private function backfillMissingSlugs(): void
  {
    if (! Schema::hasColumn('wbcms_media_folders', 'slug')) {
      return;
    }

    $usedSlugs = DB::table('wbcms_media_folders')
      ->whereNotNull('slug')
      ->where('slug', '<>', '')
      ->pluck('slug')
      ->map(fn (mixed $slug): string => (string) $slug)
      ->all();

    $used = array_fill_keys($usedSlugs, true);

    DB::table('wbcms_media_folders')
      ->select(['id', 'name', 'slug'])
      ->whereNull('slug')
      ->orWhere('slug', '')
      ->orderBy('id')
      ->get()
      ->each(function (object $folder) use (&$used): void {
        $baseSlug = Str::slug((string) ($folder->name ?? 'media-folder'));

        if ($baseSlug === '') {
          $baseSlug = 'media-folder';
        }

        $slug = $this->uniqueSlug($baseSlug, $used);
        $used[$slug] = true;

        DB::table('wbcms_media_folders')
          ->where('id', $folder->id)
          ->update(['slug' => $slug]);
      });
  }

  private function uniqueSlug(string $baseSlug, array $used): string
  {
    if (! isset($used[$baseSlug])) {
      return $baseSlug;
    }

    $suffix = 2;

    while (isset($used[$baseSlug.'-'.$suffix])) {
      $suffix++;
    }

    return $baseSlug.'-'.$suffix;
  }
};
