<?php

namespace WebBlocks\Cms\Support\Auth;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use WebBlocks\Cms\Support\Auth\Module\Schema as VerificationSchema;

final class TwoFactorSchema
{
  public function migrate(): void
  {
    app(VerificationSchema::class)->migrate();

    // Upgrade existing TOTP installations without disabling their protection or recovery codes.
    foreach (['credentials', 'enrollments'] as $name) {
      $table = 'wbcms_two_factor_'.$name;
      if (Schema::hasColumn($table, 'secret')) {
        if (! Schema::hasColumn($table, 'email')) {
          Schema::table($table, fn (Blueprint $blueprint) => $blueprint->string('email')->nullable());
        }
        if ($name === 'enrollments') {
          DB::table($table)->delete();
        } else {
          DB::table($table)->whereNull('email')->orderBy('user_id')->each(function ($row) use ($table): void {
            DB::table($table)->where('user_id', $row->user_id)->update([
              'email' => strtolower(trim((string) DB::table('users')->where('id', $row->user_id)->value('email'))),
              'generation' => (string) Str::uuid(),
            ]);
          });
        }
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('secret'));
      }
    }
    if (Schema::hasColumn('wbcms_two_factor_credentials', 'last_used_step')) {
      Schema::table('wbcms_two_factor_credentials', fn (Blueprint $table) => $table->dropColumn('last_used_step'));
    }
    foreach (['source' => 16, 'reason' => 500] as $column => $length) {
      if (Schema::hasTable('wbcms_acl_events') && ! Schema::hasColumn('wbcms_acl_events', $column)) {
        Schema::table('wbcms_acl_events', fn (Blueprint $table) => $table->string($column, $length)->nullable());
      }
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
