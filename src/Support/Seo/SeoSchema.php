<?php

namespace WebBlocks\Cms\Support\Seo;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Typed, disposable audit evidence; never a content or publication authority. */
final class SeoSchema
{
  public static function up(): void
  {
    if (! Schema::hasTable('wbcms_seo_source_versions')) {
      Schema::create('wbcms_seo_source_versions', function (Blueprint $table): void {
        $table->unsignedTinyInteger('id')->primary();
        $table->uuid('generation');
      });
    }
    DB::table('wbcms_seo_source_versions')->insertOrIgnore(['id' => 1, 'generation' => (string) Str::uuid()]);
    if (! Schema::hasTable('wbcms_seo_audits')) {
      Schema::create('wbcms_seo_audits', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
        $table->string('state', 24)->default('queued')->index();
        $table->string('phase', 24)->default('inventory');
        $table->string('source_version', 64);
        $table->string('cms_version', 32);
        $table->unsignedBigInteger('upper_page_id')->default(0);
        $table->unsignedBigInteger('cursor')->default(0);
        $table->unsignedInteger('total')->default(0);
        $table->unsignedInteger('processed')->default(0);
        $table->uuid('worker_token')->nullable();
        $table->timestamp('worker_expires_at')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
        $table->index(['site_id', 'id']);
      });
    }
    if (! Schema::hasTable('wbcms_seo_audit_locales')) {
      Schema::create('wbcms_seo_audit_locales', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('audit_id')->constrained('wbcms_seo_audits')->cascadeOnDelete();
        $table->string('locale', 16);
        $table->unique(['audit_id', 'locale']);
      });
    }
    if (! Schema::hasTable('wbcms_seo_audit_pages')) {
      Schema::create('wbcms_seo_audit_pages', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('audit_id')->constrained('wbcms_seo_audits')->cascadeOnDelete();
        // Identity at inspection time survives page deletion; the run becomes stale.
        $table->unsignedBigInteger('page_id');
        $table->string('locale', 16);
        $table->string('page_status', 32);
        $table->string('state', 24)->default('pending');
        $table->string('public_url', 2048)->nullable();
        $table->text('title')->nullable();
        $table->text('description')->nullable();
        $table->string('title_hash', 64)->nullable();
        $table->string('description_hash', 64)->nullable();
        $table->timestamp('checked_at')->nullable();
        $table->unique(['audit_id', 'page_id', 'locale'], 'seo_audit_page_locale');
        $table->index(['audit_id', 'state', 'id']);
        $table->index(['audit_id', 'locale', 'title_hash'], 'seo_audit_titles');
        $table->index(['audit_id', 'locale', 'description_hash'], 'seo_audit_descriptions');
      });
    }
    if (! Schema::hasTable('wbcms_seo_audit_findings')) {
      Schema::create('wbcms_seo_audit_findings', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('audit_id')->constrained('wbcms_seo_audits')->cascadeOnDelete();
        $table->foreignId('audit_page_id')->constrained('wbcms_seo_audit_pages')->cascadeOnDelete();
        $table->string('category', 24);
        $table->string('code', 64);
        $table->string('target', 255)->nullable();
        $table->unsignedInteger('occurrences')->default(1);
        $table->string('example', 255)->nullable();
        $table->string('origin', 24)->nullable();
        $table->unsignedBigInteger('block_id')->nullable();
        $table->unsignedBigInteger('owner_page_id')->nullable();
        $table->unsignedBigInteger('navigation_item_id')->nullable();
        $table->index(['audit_id', 'code', 'id']);
      });
    }
    if (! Schema::hasTable('wbcms_seo_audit_links')) {
      Schema::create('wbcms_seo_audit_links', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('audit_id')->constrained('wbcms_seo_audits')->cascadeOnDelete();
        $table->foreignId('audit_page_id')->constrained('wbcms_seo_audit_pages')->cascadeOnDelete();
        $table->unsignedBigInteger('block_id')->nullable();
        $table->unsignedBigInteger('owner_page_id')->nullable();
        $table->unsignedBigInteger('navigation_item_id')->nullable();
        $table->string('source', 24)->default('rendered');
        $table->boolean('is_public_source')->default(false);
        $table->string('location', 255)->nullable();
        $table->string('text', 255);
        $table->string('href', 2048);
        $table->string('state', 32);
        $table->unsignedBigInteger('target_page_id')->nullable();
        $table->string('target_locale', 16)->nullable();
        $table->string('suggested_url', 2048)->nullable();
        $table->index(['audit_id', 'target_page_id', 'target_locale'], 'seo_audit_incoming');
        $table->index(['audit_page_id', 'state', 'id']);
      });
    }
  }

  public static function down(): void
  {
    foreach (['links', 'findings', 'pages', 'locales', 'audits', 'source_versions'] as $name) {
      Schema::dropIfExists('wbcms_seo_'.(in_array($name, ['audits', 'source_versions'], true) ? $name : 'audit_'.$name));
    }
  }
}
