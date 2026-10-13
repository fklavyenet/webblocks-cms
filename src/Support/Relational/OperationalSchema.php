<?php

namespace WebBlocks\Cms\Support\Relational;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class OperationalSchema
{
  public static function ensure(): void
  {
    if (! Schema::hasTable('wbcms_plugin_lifecycles')) {
      Schema::create('wbcms_plugin_lifecycles', function (Blueprint $table): void {
        $table->id();
        $table->char('root_hash', 64);
        $table->string('handle', 190);
        $table->string('version', 190)->default('');
        $table->string('enabled_version', 190)->nullable();
        $table->string('enabled_at', 80)->nullable();
        $table->string('disabled_at', 80)->nullable();
        $table->string('failed_at', 80)->nullable();
        $table->unique(['root_hash', 'handle', 'version'], 'plugin_lifecycle_identity');
      });
    }
    if (! Schema::hasTable('wbcms_site_promotion_plans')) {
      Schema::create('wbcms_site_promotion_plans', function (Blueprint $table): void {
        $table->id();
        $table->string('token', 64)->unique();
        $table->timestamps();
      });
    }
  }
}
