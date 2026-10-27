<?php

namespace WebBlocks\Cms\Support\Auth;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TwoFactorSchema
{
  public function migrate(): void
  {
    if (! Schema::hasTable('wbcms_two_factor_credentials')) {
      Schema::create('wbcms_two_factor_credentials', function (Blueprint $table): void {
        $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
        $table->text('secret');
        $table->uuid('generation');
        $table->timestamp('confirmed_at')->nullable();
        $table->unsignedBigInteger('last_used_step')->nullable();
      });
    }
    if (! Schema::hasTable('wbcms_two_factor_enrollments')) {
      Schema::create('wbcms_two_factor_enrollments', function (Blueprint $table): void {
        $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
        $table->text('secret');
        $table->uuid('generation');
        $table->timestamp('expires_at');
      });
    }
    if (! Schema::hasTable('wbcms_two_factor_recovery_codes')) {
      Schema::create('wbcms_two_factor_recovery_codes', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->constrained('wbcms_two_factor_credentials', 'user_id')->cascadeOnDelete();
        $table->char('code_hash', 64);
        $table->unique(['user_id', 'code_hash'], 'wbcms_two_factor_code_unique');
      });
    }
    if (! Schema::hasTable('wbcms_two_factor_policy')) {
      Schema::create('wbcms_two_factor_policy', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->boolean('require_super_admin')->default(false);
      });
    }
    DB::table('wbcms_two_factor_policy')->insertOrIgnore(['id' => 1, 'require_super_admin' => false]);
    if (! Schema::hasTable('wbcms_two_factor_role_requirements')) {
      Schema::create('wbcms_two_factor_role_requirements', function (Blueprint $table): void {
        $table->foreignId('role_id')->primary()->constrained('wbcms_acl_roles')->cascadeOnDelete();
      });
    }
  }
}
