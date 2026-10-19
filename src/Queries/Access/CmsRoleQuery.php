<?php

namespace WebBlocks\Cms\Queries\Access;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Support\Access\PermissionCatalog;

final class CmsRoleQuery
{
  public function listing()
  {
    return CmsRole::query()->select('wbcms_acl_roles.*')->selectSub(DB::table('wbcms_site_user')->selectRaw('count(*)')->whereColumn('role_id', 'wbcms_acl_roles.id'), 'assignment_count')->orderByDesc('is_protected')->orderBy('name')->get();
  }

  public function permissions(CmsRole $role): array
  {
    return array_values(array_intersect(DB::table('wbcms_acl_role_permissions')->where('role_id', $role->id)->pluck('permission')->all(), array_keys(app(PermissionCatalog::class)->editable())));
  }

  public function history()
  {
    return DB::table('wbcms_acl_events as e')->leftJoin('users as actor', 'actor.id', '=', 'e.actor_id')->leftJoin('users as target', 'target.id', '=', 'e.user_id')->leftJoin('wbcms_sites as site', 'site.id', '=', 'e.site_id')->leftJoin('wbcms_acl_roles as role', 'role.id', '=', 'e.role_id')->select('e.*', 'actor.name as actor_name', 'target.name as user_name', 'site.name as site_name', 'role.name as role_name')->orderByDesc('e.id')->paginate(30);
  }
}
