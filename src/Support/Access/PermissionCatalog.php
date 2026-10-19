<?php

namespace WebBlocks\Cms\Support\Access;

use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;

final class PermissionCatalog
{
  public const GROUPS = [
    'panel' => ['access'],
    'pages' => ['read', 'create', 'update-draft', 'update-live', 'submit-review', 'return-to-draft', 'publish', 'unpublish', 'archive', 'delete', 'delete-published'],
    'translations' => ['update'], 'seo' => ['update'],
    'revisions' => ['read', 'prepare', 'apply'],
    'media' => ['read', 'upload', 'update', 'replace', 'move', 'delete'],
    'navigation' => ['read', 'update', 'delete'],
    'shared-slots' => ['read', 'update-content', 'manage', 'restore'],
    'messages' => ['read', 'update-status', 'delete'],
    'engagement' => ['read', 'moderate', 'delete'],
    'reports' => ['read'], 'site-settings' => ['read', 'update'],
    'sources' => ['read', 'preview'],
    'automatic-pages' => ['read', 'configure', 'activate'],
    'personal-tokens' => ['manage'],
  ];

  public function editable(): array
  {
    $permissions = [];
    foreach (self::GROUPS as $group => $actions) {
      foreach ($actions as $action) {
        $permissions['cms.'.$group.'.'.$action] = ['group' => $group, 'label' => __('webblocks-cms::acl.permissions.'.$group.'.'.$action)];
      }
    }
    if (app()->bound(PluginRegistry::class)) {
      foreach (app(PluginRegistry::class)->permissions(enabledOnly: true) as $items) {
        foreach ($items as $permission) {
          if ($permission->isSiteDelegable()) {
            $permissions[$permission->name()] = ['group' => 'plugins', 'label' => $permission->labelText()];
          }
        }
      }
    }

    return $permissions;
  }

  public function known(string $permission): bool
  {
    return in_array($permission, ['cms.legacy.site-assets.read', 'cms.legacy.site-assets.write', 'cms.legacy.pages.move-site'], true) || isset($this->editable()[$permission])
      || (str_starts_with($permission, 'cms.legacy-api.') && in_array(substr($permission, 15), CmsApiTokenCapabilities::ALL, true));
  }

  public function defaults(string $role): array
  {
    $all = [];
    foreach (self::GROUPS as $group => $actions) {
      foreach ($actions as $action) {
        $all[] = 'cms.'.$group.'.'.$action;
      }
    }
    if ($role === 'editor') {
      $all = array_values(array_diff($all, [
        'cms.pages.update-live', 'cms.pages.publish', 'cms.pages.archive',
        'cms.revisions.prepare', 'cms.revisions.apply', 'cms.shared-slots.manage', 'cms.shared-slots.restore',
        'cms.messages.read', 'cms.messages.update-status', 'cms.messages.delete',
        'cms.engagement.read', 'cms.engagement.moderate', 'cms.engagement.delete',
        'cms.reports.read', 'cms.site-settings.update', 'cms.automatic-pages.activate',
      ]));
    }

    return [...$all, 'cms.legacy.site-assets.read', ...($role === 'site_admin' ? ['cms.legacy.site-assets.write', 'cms.legacy.pages.move-site'] : [])];
  }

  public function capabilityPermissions(): array
  {
    return array_filter(array_combine(CmsApiTokenCapabilities::ALL, array_map($this->capabilityPermission(...), CmsApiTokenCapabilities::ALL)));
  }

  public function capabilityPermission(string $capability): ?string
  {
    return match ($capability) {
      'content.read', 'content.validate' => 'cms.pages.read',
      'content.apply', 'content.blocks.delete' => 'cms.pages.update-draft',
      'content.publish' => 'cms.pages.publish', 'pages.delete' => 'cms.pages.delete',
      'navigation.write' => 'cms.navigation.update', 'navigation.delete' => 'cms.navigation.delete',
      'shared-slots.write' => 'cms.shared-slots.update-content', 'shared-slots.delete' => 'cms.shared-slots.manage',
      'media.read' => 'cms.media.read', 'media.write' => 'cms.media.update',
      'media.upload' => 'cms.media.upload', 'media.replace' => 'cms.media.replace',
      'media.move' => 'cms.media.move', 'media.delete' => 'cms.media.delete',
      'site-settings.write' => 'cms.site-settings.update',
      'engagement.read' => 'cms.engagement.read', 'engagement.moderate' => 'cms.engagement.moderate',
      default => null,
    };
  }

  /** Transport capabilities admit requests; resource policies still check the exact operation. */
  public function capabilityGrantPermissions(string $capability): array
  {
    return match ($capability) {
      'content.read' => ['cms.pages.read', 'cms.revisions.read', 'cms.navigation.read', 'cms.shared-slots.read', 'cms.site-settings.read', 'cms.sources.read'],
      'content.apply' => ['cms.pages.create', 'cms.pages.update-draft', 'cms.pages.update-live', 'cms.pages.publish', 'cms.translations.update', 'cms.revisions.prepare', 'cms.revisions.apply', 'cms.shared-slots.update-content'],
      'content.publish' => ['cms.pages.publish', 'cms.pages.archive', 'cms.shared-slots.update-content'],
      'content.blocks.delete' => ['cms.pages.update-draft', 'cms.pages.update-live', 'cms.shared-slots.update-content'],
      default => array_filter([$this->capabilityPermission($capability)]),
    };
  }
}
