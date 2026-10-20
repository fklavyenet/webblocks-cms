<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use WebBlocks\Cms\Support\Access\CmsAuthorization;

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

      $acl = app(CmsAuthorization::class);
      if ($acl->ready()) {
        if (! $user || ! ($user->is_active ?? true)) {
        return false;
        }
        $siteIds = $acl->siteIds($user);

        return $siteIds->contains(function ($siteId) use ($user, $declaredPermission, $acl, $permission) {
          return ($declaredPermission->isSiteDelegable() && $acl->allows($user, $permission, $siteId))
            || in_array($acl->legacyRole($user, $siteId), $declaredPermission->roleNames(), true);
        });
      }
      $role = $this->normalizedRole($user);

      return $role !== null && in_array($role, $declaredPermission->roleNames(), true);
    }

    return false;
  }

  public function permittedSiteIds($user, string $permission): Collection
  {
    $acl = app(CmsAuthorization::class);
    $definition = $this->declaredPluginPermission($permission);
    if (! $definition || ! $acl->ready()) {
    return collect();
    }

    return $acl->siteIds($user)->filter(fn ($siteId) => $this->isSuperAdmin($user)
      || ($definition->isSiteDelegable() && $acl->allows($user, $permission, $siteId))
      || in_array($acl->legacyRole($user, $siteId), $definition->roleNames(), true))->values();
  }

  public function allowsInSiteContext(PluginPermission $definition, bool $hasPanelAccess, bool $superAdmin, bool $hasGrant, ?string $protectedRole): bool
  {
    return $hasPanelAccess && ($superAdmin
      || ($definition->isSiteDelegable() && $hasGrant)
      || in_array($protectedRole, $definition->roleNames(), true));
  }

  public function isSuperAdmin(?Authenticatable $user): bool
  {
    if ($user === null || ! ($user->is_active ?? true)) {
    return false;
    }
    if (app(CmsAuthorization::class)->ready()) {
      return app(CmsAuthorization::class)->isSuperAdmin($user);
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
