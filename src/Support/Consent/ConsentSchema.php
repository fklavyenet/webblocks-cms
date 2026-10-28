<?php

namespace WebBlocks\Cms\Support\Consent;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\System\SystemSettings;

final class ConsentSchema
{
  public static function migrate(): void
  {
    $newInstallation = ! Schema::hasTable('wbcms_consent_settings');
    if ($newInstallation) {
      Schema::create('wbcms_consent_settings', function (Blueprint $table): void {
        $table->foreignId('site_id')->primary()->constrained('wbcms_sites')->cascadeOnDelete();
        $table->boolean('enabled')->default(true);
        $table->unsignedSmallInteger('lifetime_days')->default(180);
        $table->string('privacy_url', 2048)->nullable();
        $table->string('position', 16)->default('bottom');
        $table->timestamps();
      });
      $enabled = (bool) config('cms.visitor_reports.enabled', true) && app(SystemSettings::class)->visitorConsentBannerEnabled();
      DB::table('wbcms_sites')->orderBy('id')->each(fn ($site) => DB::table('wbcms_consent_settings')->insertOrIgnore([
        'site_id' => $site->id, 'enabled' => $enabled, 'lifetime_days' => max(1, min(730, (int) config('cms.visitor_reports.consent_cookie_lifetime_days', 180))),
        'position' => 'bottom', 'created_at' => now(), 'updated_at' => now(),
      ]));
    }
    if ($newInstallation && Schema::hasTable('wbcms_acl_roles')) {
      foreach (DB::table('wbcms_acl_roles')->where('is_protected', true)->whereIn('handle', ['site_admin', 'editor'])->get() as $role) {
        foreach ($role->handle === 'site_admin' ? ['read', 'update'] : ['read'] as $operation) {
          DB::table('wbcms_acl_role_permissions')->insertOrIgnore(['role_id' => $role->id, 'permission' => 'cms.consent.'.$operation]);
        }
      }
    }
    if (! Schema::hasTable('wbcms_consent_services')) {
      Schema::create('wbcms_consent_services', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
        $table->string('handle', 64);
        $table->string('provider', 64);
        $table->string('category', 32);
        $table->string('name', 160);
        $table->text('description')->nullable();
        $table->string('privacy_url', 2048)->nullable();
        $table->string('script_url', 2048)->nullable();
        $table->boolean('enabled')->default(true);
        $table->uuid('revision');
        $table->unique(['site_id', 'handle']);
        $table->timestamps();
      });
    }
    if (! Schema::hasTable('wbcms_consent_translations')) {
      Schema::create('wbcms_consent_translations', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
        $table->string('locale', 16);
        $table->string('key', 100);
        $table->text('value');
        $table->unique(['site_id', 'locale', 'key'], 'wbcms_consent_translation_unique');
      });
    }
    if (! Schema::hasTable('wbcms_consent_cookies')) {
      Schema::create('wbcms_consent_cookies', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('service_id')->constrained('wbcms_consent_services')->cascadeOnDelete();
        $table->string('name', 160);
        $table->string('path', 255)->default('/');
        $table->string('duration', 160)->nullable();
        $table->unique(['service_id', 'name', 'path'], 'wbcms_consent_cookie_unique');
      });
    }
  }
}
