<?php

namespace WebBlocks\Cms\Actions\Access;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Policies\CmsRolePolicy;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Access\PermissionCatalog;

final class WriteCmsAccess
{
  public function locked(callable $operation): mixed
  {
    return DB::transaction(function () use ($operation) {
      DB::table('wbcms_acl_locks')->where('id', 1)->lockForUpdate()->first();

      return $operation();
    });
  }

  public function role($actor, array $data, ?CmsRole $role = null): CmsRole
  {
    abort_unless(app(CmsRolePolicy::class)->manage($actor) && (! $role || ! $role->is_protected), 403);

    return $this->locked(function () use ($actor, $data, $role) {
      $this->authorizeActor($actor);
      if ($role) {
        $role = CmsRole::query()->lockForUpdate()->findOrFail($role->id);
        if ((int) $data['version'] !== $role->version) {
        throw ValidationException::withMessages(['version' => __('webblocks-cms::acl.stale')]);
        }
      }
      $permissions = array_values(array_unique($data['permissions'] ?? []));
      foreach ($permissions as $permission) {
      abort_unless(isset(app(PermissionCatalog::class)->editable()[$permission]), 422);
      }
      $old = $role ? DB::table('wbcms_acl_role_permissions')->where('role_id', $role->id)->pluck('permission')->all() : [];
      $before = $role?->name;
      $role ??= new CmsRole(['handle' => 'custom-'.Str::uuid(), 'is_protected' => false, 'version' => 0]);
      $role->fill(['name' => $data['name'], 'description' => $data['description'] ?? null, 'version' => $role->version + 1])->save();
      DB::table('wbcms_acl_role_permissions')->where('role_id', $role->id)->delete();
      foreach ($permissions as $permission) {
      DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $role->id, 'permission' => $permission]);
      }
      $this->event($actor, 'role.saved', $role->id, before: $before, after: $role->name);
      foreach (array_diff($permissions, $old) as $permission) {
      $this->event($actor, 'permission.granted', $role->id, permission: $permission);
      }
      foreach (array_diff($old, $permissions) as $permission) {
      $this->event($actor, 'permission.revoked', $role->id, permission: $permission);
      }

      return $role;
    });
  }

  public function deleteRole($actor, CmsRole $role, int $version): void
  {
    abort_unless(app(CmsRolePolicy::class)->update($actor, $role), 403);
    $this->locked(function () use ($actor, $role, $version): void {
      $this->authorizeActor($actor);
      $role = CmsRole::query()->lockForUpdate()->findOrFail($role->id);
      if ($role->version !== $version || DB::table('wbcms_site_user')->where('role_id', $role->id)->exists()) {
        throw ValidationException::withMessages(['role' => __('webblocks-cms::acl.in_use')]);
      }
      $this->event($actor, 'role.deleted', $role->id, before: $role->name);
      $role->delete();
    });
  }

  public function user($actor, $user, array $data): mixed
  {
    abort_unless(app(CmsRolePolicy::class)->manage($actor), 403);

    return $this->locked(function () use ($actor, $user, $data) {
      $this->authorizeActor($actor);
      if ($user->exists) {
      $user = $user->newQuery()->lockForUpdate()->findOrFail($user->id);
      }
      $root = ($data['role'] ?? '') === 'super_admin';
      if ($user->exists && $this->lastAdmin($user) && (! $root || ! ($data['is_active'] ?? false))) {
        throw ValidationException::withMessages(['role' => __('webblocks-cms::acl.last_admin')]);
      }
      $sites = $data['site_ids'] ?? [];
      $siteRoles = $data['site_roles'] ?? [];
      unset($data['site_ids'], $data['site_roles']);
      if (empty($data['password'])) {
      unset($data['password']);
      }
      $user->fill($data)->save();
      if ($root) {
      DB::table('wbcms_acl_system_administrators')->insertOrIgnore(['user_id' => $user->id]);
      } else {
      DB::table('wbcms_acl_system_administrators')->where('user_id', $user->id)->delete();
      }
      $assignments = [];
      foreach ($root ? [] : $sites as $siteId) {
        $roleId = $siteRoles[$siteId] ?? null;
        $roleId = $roleId ?: CmsRole::query()->where('is_protected', true)->where('handle', $data['role'])->value('id');
        $role = CmsRole::query()->findOrFail($roleId);
        $assignments[(int) $siteId] = ['role_id' => $role->id];
      }
      $old = DB::table('wbcms_site_user')->where('user_id', $user->id)->pluck('role_id', 'site_id')->all();
      $user->sites()->sync($assignments);
      foreach (array_unique([...array_keys($old), ...array_keys($assignments)]) as $siteId) {
        $next = $assignments[$siteId]['role_id'] ?? null;
        if (($old[$siteId] ?? null) != $next) {
        $this->event($actor, 'assignment.changed', userId: $user->id, siteId: $siteId, before: isset($old[$siteId]) ? (string) $old[$siteId] : null, after: $next === null ? null : (string) $next);
        }
      }
      $this->event($actor, 'user.access.saved', userId: $user->id, after: $root ? 'super_admin' : 'site_roles');

      return $user;
    });
  }

  public function deleteUser($actor, $user): void
  {
    abort_unless($actor->is($user) || app(CmsRolePolicy::class)->manage($actor), 403);
    $this->locked(function () use ($actor, $user): void {
      if ($actor->is($user)) {
        abort_unless(DB::table('users')->where('id', $actor->id)->where('is_active', true)->exists(), 403);
      } else {
        $this->authorizeActor($actor);
      }
      $user = $user->newQuery()->lockForUpdate()->findOrFail($user->id);
      if ($this->lastAdmin($user)) {
      throw ValidationException::withMessages(['user' => __('webblocks-cms::acl.last_admin')]);
      }
      $this->event($actor, 'user.deleted', userId: $user->id);
      $user->delete();
    });
  }

  private function authorizeActor($actor): void
  {
    abort_unless(DB::table('users')->where('id', $actor->id)->where('is_active', true)->exists()
      && app(CmsAuthorization::class)->isSuperAdmin($actor), 403);
  }

  private function lastAdmin($user): bool
  {
    return app(CmsAuthorization::class)->isSuperAdmin($user) && (bool) $user->is_active
      && DB::table('wbcms_acl_system_administrators as a')->join('users as u', 'u.id', '=', 'a.user_id')->where('u.is_active', true)->count() <= 1;
  }

  private function event($actor, string $action, ?int $roleId = null, ?int $userId = null, ?int $siteId = null, ?string $permission = null, ?string $before = null, ?string $after = null): void
  {
    DB::table('wbcms_acl_events')->insert(['actor_id' => $actor->id, 'action' => $action, 'role_id' => $roleId, 'user_id' => $userId, 'site_id' => $siteId, 'permission' => $permission, 'before_value' => $before, 'after_value' => $after, 'created_at' => now()]);
  }
}
