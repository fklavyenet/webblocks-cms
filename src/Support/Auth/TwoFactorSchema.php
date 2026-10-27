<?php

namespace WebBlocks\Cms\Support\Auth;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class TwoFactorSchema
{
  public function migrate(): void
  {
    if (! Schema::hasTable('wbcms_two_factor_credentials')) {
      Schema::create('wbcms_two_factor_credentials', function (Blueprint $table): void {
        $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
        $table->string('email');
        $table->uuid('generation');
        $table->timestamp('confirmed_at')->nullable();
      });
    }
    if (! Schema::hasTable('wbcms_two_factor_enrollments')) {
      Schema::create('wbcms_two_factor_enrollments', function (Blueprint $table): void {
        $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
        $table->string('email');
        $table->uuid('generation');
        $table->timestamp('expires_at');
      });
    }
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
    if (! Schema::hasTable('wbcms_two_factor_email_codes')) {
      Schema::create('wbcms_two_factor_email_codes', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->string('purpose', 16);
        $table->string('email');
        $table->char('binding', 64);
        $table->char('code_hash', 64);
        $table->unsignedTinyInteger('attempts')->default(0);
        $table->timestamp('sent_at');
        $table->timestamp('expires_at');
        $table->timestamp('consumed_at')->nullable();
        $table->index(['user_id', 'sent_at']);
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
    if (! Schema::hasTable('wbcms_two_factor_account_states')) {
      Schema::create('wbcms_two_factor_account_states', function (Blueprint $table): void {
        $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
        $table->uuid('session_version');
        $table->boolean('enrollment_required')->default(true);
        $table->timestamp('reset_at');
      });
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
