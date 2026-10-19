<?php

namespace WebBlocks\Cms\Support\InternalApiTokens;

use App\Models\User;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Access\PermissionCatalog;

class PersonalApiTokenPolicy
{
  private const SYSTEM_ONLY = [
    CmsApiTokenCapabilities::SYSTEM_UPDATES_READ,
    CmsApiTokenCapabilities::SYSTEM_UPDATES_RUN,
    CmsApiTokenCapabilities::ADMIN_RENDER,
    CmsApiTokenCapabilities::BACKUPS_CREATE,
    CmsApiTokenCapabilities::BACKUPS_READ,
    CmsApiTokenCapabilities::BACKUPS_SETTINGS_WRITE,
    CmsApiTokenCapabilities::BACKUPS_DELETE,
    CmsApiTokenCapabilities::SYSTEM_HEALTH_READ,
    CmsApiTokenCapabilities::MAINTENANCE_READ,
    CmsApiTokenCapabilities::MAINTENANCE_SETTINGS_WRITE,
    CmsApiTokenCapabilities::MAINTENANCE_DELETE,
    CmsApiTokenCapabilities::PLUGINS_READ,
    CmsApiTokenCapabilities::PLUGINS_INSTALL,
    CmsApiTokenCapabilities::PLUGINS_MANAGE,
    CmsApiTokenCapabilities::PLUGINS_SETUP,
    CmsApiTokenCapabilities::PLUGINS_UNINSTALL,
    CmsApiTokenCapabilities::APPLICATIONS_READ,
    CmsApiTokenCapabilities::APPLICATIONS_WRITE,
    CmsApiTokenCapabilities::APPLICATIONS_DELETE,
    CmsApiTokenCapabilities::DOMAINS_WRITE,
    CmsApiTokenCapabilities::DOMAINS_DELETE,
    CmsApiTokenCapabilities::SITE_ASSETS_READ,
    CmsApiTokenCapabilities::SITE_ASSETS_WRITE,
    CmsApiTokenCapabilities::PAGE_ASSETS_WRITE,
  ];

  private const SITE_ADMIN_ONLY = [
    CmsApiTokenCapabilities::CONTENT_PUBLISH,
    CmsApiTokenCapabilities::SITE_SETTINGS_WRITE,
  ];

  public function grantable(User $user): array
  {
    $acl = app(CmsAuthorization::class);
    if ($acl->ready()) {
      return array_values(array_filter(array_diff(CmsApiTokenCapabilities::ALL, self::SYSTEM_ONLY), function ($capability) use ($acl, $user) {
        $permissions = app(PermissionCatalog::class)->capabilityGrantPermissions($capability);

        return collect($permissions)->contains(fn ($permission) => $acl->allows($user, $permission)) || $acl->allows($user, 'cms.legacy-api.'.$capability);
      }));
    }
    $capabilities = array_values(array_diff(CmsApiTokenCapabilities::ALL, self::SYSTEM_ONLY));

    if ($user->isEditor()) {
      $capabilities = array_values(array_diff($capabilities, self::SITE_ADMIN_ONLY));
    }

    return $capabilities;
  }

  public static function legacyCapabilities(string $role): array
  {
    return array_values(array_diff(CmsApiTokenCapabilities::ALL, self::SYSTEM_ONLY, $role === 'editor' ? self::SITE_ADMIN_ONLY : []));
  }

  public function canUse(User $user, string $capability): bool
  {
    return in_array($capability, $this->grantable($user), true);
  }
}
