<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\PageTranslation;

return new class extends Migration
{
  /**
     * Run the migrations.
     */
  public function up(): void
  {
    if (! Schema::hasColumns('wbcms_pages', ['title', 'slug'])) {
      return;
    }

    $this->dropIndexIfExists('wbcms_pages', 'wbcms_pages_slug_unique');
    $this->dropIndexIfExists('wbcms_pages', 'wbcms_pages_slug_index');

    Schema::table('wbcms_pages', function (Blueprint $table) {
      $table->dropColumn(['title', 'slug']);
    });
  }

  /**
     * Reverse the migrations.
     */
  public function down(): void
  {
    if (Schema::hasColumns('wbcms_pages', ['title', 'slug'])) {
      return;
    }

    Schema::table('wbcms_pages', function (Blueprint $table) {
      $table->string('title')->nullable()->after('site_id');
      $table->string('slug')->nullable()->after('title');
      $table->index('slug');
    });

    $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');

    if (! $defaultLocaleId) {
      return;
    }

    PageTranslation::query()
      ->where('locale_id', $defaultLocaleId)
      ->select(['page_id', 'name', 'slug'])
      ->orderBy('page_id')
      ->get()
      ->each(fn (PageTranslation $translation) => DB::table('wbcms_pages')
        ->where('id', $translation->page_id)
        ->update([
          'title' => $translation->name,
          'slug' => $translation->slug,
        ]));
  }

  private function dropIndexIfExists(string $table, string $index): void
  {
    $driver = DB::getDriverName();

    $exists = match ($driver) {
      'mysql' => DB::table('information_schema.statistics')
        ->where('table_schema', DB::raw('database()'))
        ->where('table_name', $table)
        ->where('index_name', $index)
        ->exists(),
      'sqlite' => collect(DB::select("pragma index_list('{$table}')"))
        ->contains(fn (object $row) => ($row->name ?? null) === $index),
      default => false,
    };

    if (! $exists) {
      return;
    }

    Schema::table($table, function (Blueprint $table) use ($index) {
      $table->dropIndex($index);
    });
  }
};
