<?php

namespace WebBlocks\Cms\Queries\Access;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Access\PermissionCatalog;
use WebBlocks\Cms\Support\Plugins\PluginAccessResolver;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;

final class CmsPermissionQuery
{
  public function __construct(private CmsAuthorization $acl, private PermissionCatalog $catalog) {}

  public function inspect(?User $user, ?Site $site, array $filters): array
  {
    $effective = [];
    $visible = [];
    $needle = $this->searchText($filters['q']);
    $assignedRole = null;
    $superAdmin = false;
    if ($user && $site) {
      // This context exists only for this inspection, never across requests or writes.
      $ready = $this->acl->ready();
      $superAdmin = $this->acl->isSuperAdmin($user);
      $assignedRole = $ready ? CmsRole::query()->select('wbcms_acl_roles.*')
        ->join('wbcms_site_user as membership', 'membership.role_id', '=', 'wbcms_acl_roles.id')
        ->where('membership.user_id', $user->getAuthIdentifier())->where('membership.site_id', $site->id)->first() : null;
      $grants = $assignedRole ? DB::table('wbcms_acl_role_permissions')->where('role_id', $assignedRole->id)->pluck('permission')->flip()->all() : [];
      $panel = (bool) $user->is_active && ($superAdmin || isset($grants['cms.panel.access']));
      $legacyRole = $assignedRole?->is_protected ? $assignedRole->handle : null;
      $pluginPermissions = app()->bound(PluginRegistry::class) ? app(PluginRegistry::class)->permissions(enabledOnly: true) : [];
      $pluginDefinitions = [];
      foreach ($pluginPermissions as $items) {
        $pluginDefinitions += $items;
      }
      foreach ($this->catalog->editable($pluginPermissions) as $key => $definition) {
        $allowed = $definition['group'] === 'plugins'
          ? ($ready && app(PluginAccessResolver::class)->allowsInSiteContext($pluginDefinitions[$key], $panel, $superAdmin, isset($grants[$key]), $legacyRole))
          : ($ready ? $panel && ($superAdmin || isset($grants[$key])) : $this->acl->allows($user, $key, $site->id));
        $effective[$key] = $allowed;
        if (($filters['status'] === 'allowed' && ! $allowed) || ($filters['status'] === 'denied' && $allowed)) {
          continue;
        }
        $haystack = $this->searchText($key.' '.$definition['label'].' '.__('webblocks-cms::acl.groups.'.$definition['group']));
        if ($needle !== '' && ! str_contains($haystack, $needle)) {
          continue;
        }
        $visible[$key] = [...$definition, 'allowed' => $allowed];
      }
    }
    $grantedCount = count(array_filter($effective));

    return [
      'permissionGroups' => collect($visible)->groupBy('group', preserveKeys: true),
      'totalCount' => count($effective), 'filteredCount' => count($visible),
      'grantedCount' => $grantedCount, 'deniedCount' => count($effective) - $grantedCount,
      'assignedRole' => $assignedRole,
      'subjectIsSuperAdmin' => $superAdmin,
    ];
  }

  private function searchText(string $value): string
  {
    $normalized = Str::ascii($value, app()->getLocale());

    return Str::lower($normalized !== '' ? $normalized : $value);
  }
}
