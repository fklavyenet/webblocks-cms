<?php

namespace WebBlocks\Cms\Support\Access;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\InternalApiTokens\PersonalApiTokenPolicy;

final class AclSchema
{
  public function migrate(): void
  {
    if (! Schema::hasTable('wbcms_acl_roles')) {
      Schema::create('wbcms_acl_roles', function (Blueprint $table): void {
        $table->id();
        $table->string('handle', 100)->unique();
        $table->string('name', 160);
        $table->text('description')->nullable();
        $table->boolean('is_protected')->default(false);
        $table->unsignedInteger('version')->default(1);
        $table->timestamps();
      });
    }
    if (! Schema::hasTable('wbcms_acl_role_permissions')) {
      Schema::create('wbcms_acl_role_permissions', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('role_id')->constrained('wbcms_acl_roles')->cascadeOnDelete();
        $table->string('permission', 190);
        $table->unique(['role_id', 'permission']);
      });
    }
    if (! Schema::hasTable('wbcms_acl_system_administrators')) {
      Schema::create('wbcms_acl_system_administrators', function (Blueprint $table): void {
        $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
      });
    }
    if (! Schema::hasTable('wbcms_acl_events')) {
      Schema::create('wbcms_acl_events', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('actor_id')->nullable();
        $table->string('action', 40);
        $table->unsignedBigInteger('role_id')->nullable();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->unsignedBigInteger('site_id')->nullable();
        $table->string('permission', 190)->nullable();
        $table->text('before_value')->nullable();
        $table->text('after_value')->nullable();
        $table->timestamp('created_at');
      });
    }
    if (! Schema::hasTable('wbcms_acl_locks')) {
      Schema::create('wbcms_acl_locks', function (Blueprint $table): void {
      $table->unsignedInteger('id')->primary();
      });
    }
    DB::table('wbcms_acl_locks')->insertOrIgnore(['id' => 1]);
    foreach (['site_admin' => 'Site Admin', 'editor' => 'Editor'] as $handle => $name) {
      DB::transaction(function () use ($handle, $name): void {
      if (! DB::table('wbcms_acl_roles')->where('handle', $handle)->exists()) {
        $id = DB::table('wbcms_acl_roles')->insertGetId(['handle' => $handle, 'name' => $name, 'is_protected' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([...(new PermissionCatalog)->defaults($handle), ...array_map(fn ($capability) => 'cms.legacy-api.'.$capability, PersonalApiTokenPolicy::legacyCapabilities($handle))] as $permission) {
          DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $id, 'permission' => $permission]);
        }
      }
      });
    }
    if (Schema::hasTable('wbcms_site_user') && ! Schema::hasColumn('wbcms_site_user', 'role_id')) {
      Schema::table('wbcms_site_user', function (Blueprint $table): void {
        $table->foreignId('role_id')->nullable()->constrained('wbcms_acl_roles')->restrictOnDelete();
      });
    }
    DB::transaction(function (): void {
      DB::table('wbcms_acl_locks')->where('id', 1)->lockForUpdate()->first();
      if (DB::table('wbcms_acl_locks')->where('id', 2)->exists()) {
      return;
      }
      foreach (DB::table('wbcms_acl_roles')->whereIn('handle', ['site_admin', 'editor'])->get() as $role) {
        DB::table('wbcms_site_user')->whereNull('role_id')->whereIn('user_id', DB::table('users')->select('id')->where('role', $role->handle))->update(['role_id' => $role->id]);
      }
      foreach (DB::table('users')->where(function ($query): void {
        $query->where('role', 'super_admin')->orWhere(fn ($q) => $q->where('is_admin', true)->where(fn ($r) => $r->whereNull('role')->orWhereNotIn('role', ['super_admin', 'site_admin', 'editor'])));
      })->pluck('id') as $id) {
        DB::table('wbcms_acl_system_administrators')->insertOrIgnore(['user_id' => $id]);
      }
      DB::table('wbcms_acl_locks')->insert(['id' => 2]);
    });
  }
}
