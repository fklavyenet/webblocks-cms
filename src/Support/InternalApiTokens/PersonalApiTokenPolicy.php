<?php

namespace WebBlocks\Cms\Support\InternalApiTokens;

use App\Models\User;
use WebBlocks\Cms\Policies\NotePolicy;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Access\PermissionCatalog;

class PersonalApiTokenPolicy
{
  private const SYSTEM_ONLY = [
    CmsApiTokenCapabilities::CONSENT_CODE_WRITE,
    CmsApiTokenCapabilities::PANEL_LANGUAGES_MANAGE,
    CmsApiTokenCapabilities::THEMES_MANAGE,
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
    CmsApiTokenCapabilities::CONSENT_WRITE,
    CmsApiTokenCapabilities::CONTENT_PUBLISH,
    CmsApiTokenCapabilities::SITE_SETTINGS_WRITE,
  ];

  public function grantable(User $user): array
  {
    return array_values(array_filter(CmsApiTokenCapabilities::ALL, fn (string $capability) => $this->canUse($user, $capability)));
  }

  public static function legacyCapabilities(string $role): array
  {
    return array_values(array_diff(CmsApiTokenCapabilities::ALL, self::SYSTEM_ONLY, CmsApiTokenCapabilities::PERSONAL_ONLY, $role === 'editor' ? self::SITE_ADMIN_ONLY : []));
  }

  public function canUse(User $user, string $capability): bool
  {
    if (! in_array($capability, CmsApiTokenCapabilities::ALL, true) || in_array($capability, self::SYSTEM_ONLY, true)) {
      return false;
    }

    if (in_array($capability, CmsApiTokenCapabilities::PERSONAL_ONLY, true)) {
      return app(NotePolicy::class)->access($user);
    }

    $acl = app(CmsAuthorization::class);
    if (! $acl->ready()) {
      return ! $user->isEditor() || ! in_array($capability, self::SITE_ADMIN_ONLY, true);
    }

    $permissions = app(PermissionCatalog::class)->capabilityGrantPermissions($capability);

    return collect($permissions)->contains(fn ($permission) => $acl->allows($user, $permission))
      || $acl->allows($user, 'cms.legacy-api.'.$capability);
  }
}
