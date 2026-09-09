<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

class PluginAccessResolver
{
  public function canAccessSystem(?Authenticatable $user): bool
  {
    return $this->isSuperAdmin($user);
  }

  public function canAccessPluginPermission(?Authenticatable $user, string $permission, ?PluginRegistry $registry = null): bool
  {
    if ($declaredPermission = $this->declaredPluginPermission($permission, $registry)) {
      if ($this->isSuperAdmin($user)) {
        return true;
      }

      $role = $this->normalizedRole($user);

      return $role !== null && in_array($role, $declaredPermission->roleNames(), true);
    }

    return Gate::has($permission) && (bool) $user?->can($permission);
  }

  public function isSuperAdmin(?Authenticatable $user): bool
  {
    if ($user === null) {
      return false;
    }

    if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
      return true;
    }

    if (method_exists($user, 'normalizedRole') && $user->normalizedRole() === 'super_admin') {
      return true;
    }

    if (($user->role ?? null) === 'super_admin') {
      return true;
    }

    return (bool) ($user->is_admin ?? false);
  }

  private function declaredPluginPermission(string $permission, ?PluginRegistry $registry = null): ?PluginPermission
  {
    if ($registry === null && ! app()->bound(PluginRegistry::class)) {
      return null;
    }

    $registry ??= app(PluginRegistry::class);

    foreach ($registry->permissions(enabledOnly: true) as $permissions) {
      if (array_key_exists($permission, $permissions)) {
        return $permissions[$permission];
      }
    }

    return null;
  }

  private function normalizedRole(?Authenticatable $user): ?string
  {
    if ($user === null) {
      return null;
    }

    if (method_exists($user, 'normalizedRole')) {
      $role = $user->normalizedRole();

      return is_string($role) && $role !== '' ? $role : null;
    }

    $role = $user->role ?? null;

    return is_string($role) && $role !== '' ? $role : null;
  }
}
