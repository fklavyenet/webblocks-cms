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
    if ($this->isDeclaredPluginPermission($permission, $registry)) {
      return $this->isSuperAdmin($user);
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

  private function isDeclaredPluginPermission(string $permission, ?PluginRegistry $registry = null): bool
  {
    if ($registry === null && ! app()->bound(PluginRegistry::class)) {
      return false;
    }

    $registry ??= app(PluginRegistry::class);

    foreach ($registry->permissions(enabledOnly: true) as $permissions) {
      if (array_key_exists($permission, $permissions)) {
        return true;
      }
    }

    return false;
  }
}
