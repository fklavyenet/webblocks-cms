<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('wbcms_sites', function (Blueprint $table) {
      $table->id();
      $table->string('name');
      $table->string('handle')->unique();
      $table->string('domain')->nullable()->unique();
      $table->boolean('is_primary')->default(false);
      $table->timestamps();
    });

    Schema::create('wbcms_locales', function (Blueprint $table) {
      $table->id();
      $table->string('code')->unique();
      $table->string('name');
      $table->boolean('is_default')->default(false);
      $table->boolean('is_enabled')->default(true);
      $table->timestamps();
    });

    Schema::create('wbcms_site_locales', function (Blueprint $table) {
      $table->id();
      $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
      $table->foreignId('locale_id')->constrained('wbcms_locales')->cascadeOnDelete();
      $table->boolean('is_enabled')->default(true);
      $table->timestamps();

      $table->unique(['site_id', 'locale_id']);
    });

    $now = now();

    DB::table('wbcms_sites')->updateOrInsert(
      ['handle' => 'default'],
      [
        'name' => 'Default Site',
        'domain' => null,
        'is_primary' => true,
        'created_at' => $now,
        'updated_at' => $now,
      ],
    );

    DB::table('wbcms_locales')->updateOrInsert(
      ['code' => 'en'],
      [
        'name' => 'English',
        'is_default' => true,
        'is_enabled' => true,
        'created_at' => $now,
        'updated_at' => $now,
      ],
    );

    $defaultSiteId = (int) DB::table('wbcms_sites')->where('handle', 'default')->value('id');
    $defaultLocaleId = (int) DB::table('wbcms_locales')->where('code', 'en')->value('id');

    DB::table('wbcms_site_locales')->updateOrInsert(
      ['site_id' => $defaultSiteId, 'locale_id' => $defaultLocaleId],
      [
        'is_enabled' => true,
        'created_at' => $now,
        'updated_at' => $now,
      ],
    );

    Schema::table('wbcms_pages', function (Blueprint $table) use ($defaultSiteId) {
      $table->foreignId('site_id')->default($defaultSiteId)->after('id')->constrained('wbcms_sites')->cascadeOnDelete();
    });

    Schema::table('wbcms_pages', function (Blueprint $table) {
      $table->dropUnique('wbcms_pages_slug_unique');
      $table->index('slug');
    });

    Schema::create('wbcms_page_translations', function (Blueprint $table) {
      $table->id();
      $table->foreignId('page_id')->constrained('wbcms_pages')->cascadeOnDelete();
      $table->foreignId('locale_id')->constrained('wbcms_locales')->cascadeOnDelete();
      $table->string('name');
      $table->string('slug');
      $table->string('path');
      $table->timestamps();

      $table->unique(['page_id', 'locale_id']);
      $table->index(['locale_id', 'slug']);
      $table->index(['locale_id', 'path']);
    });

    $pages = DB::table('wbcms_pages')
      ->select(['id', 'site_id', 'title', 'slug', 'created_at', 'updated_at'])
      ->orderBy('id')
      ->get();

    foreach ($pages as $page) {
      $slug = (string) $page->slug;

      DB::table('wbcms_page_translations')->updateOrInsert(
        ['page_id' => $page->id, 'locale_id' => $defaultLocaleId],
        [
          'name' => $page->title,
          'slug' => $slug,
          'path' => $slug === 'home' ? '/' : '/p/'.$slug,
          'created_at' => $page->created_at ?? $now,
          'updated_at' => $page->updated_at ?? $now,
        ],
      );
    }
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_page_translations');

    Schema::table('wbcms_pages', function (Blueprint $table) {
      $table->dropIndex(['slug']);
      $table->dropConstrainedForeignId('site_id');
      $table->unique('slug');
    });

    Schema::dropIfExists('wbcms_site_locales');
    Schema::dropIfExists('wbcms_locales');
    Schema::dropIfExists('wbcms_sites');
  }
};
