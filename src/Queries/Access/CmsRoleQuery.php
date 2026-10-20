<?php

namespace WebBlocks\Cms\Queries\Access;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Support\Access\PermissionCatalog;
use WebBlocks\Cms\Support\Admin\AdminPagination;

final class CmsRoleQuery
{
  public const HISTORY_ACTIONS = ['role.saved', 'permission.granted', 'permission.revoked', 'role.deleted', 'assignment.changed', 'user.access.saved', 'user.deleted'];

  public function listing(array $filters = [])
  {
    return CmsRole::query()->select('wbcms_acl_roles.*')
      ->selectSub(DB::table('wbcms_site_user')->selectRaw('count(*)')->whereColumn('role_id', 'wbcms_acl_roles.id'), 'assignment_count')
      ->when(($filters['q'] ?? '') !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$filters['q'].'%')->orWhere('description', 'like', '%'.$filters['q'].'%')))
      ->when($filters['type'] ?? '', fn ($query, $type) => $query->where('is_protected', $type === 'protected'))
      ->orderByDesc('is_protected')->orderBy('name')->orderBy('id')
      ->paginate(AdminPagination::perPage(), ['*'], 'roles_page')->withQueryString();
  }

  public function total(): int
  {
    return CmsRole::query()->count();
  }

  public function permissions(CmsRole $role): array
  {
    return array_values(array_intersect(DB::table('wbcms_acl_role_permissions')->where('role_id', $role->id)->pluck('permission')->all(), array_keys(app(PermissionCatalog::class)->editable())));
  }

  public function history(array $filters = [])
  {
    return DB::table('wbcms_acl_events as e')
      ->leftJoin('users as actor', 'actor.id', '=', 'e.actor_id')
      ->leftJoin('users as target', 'target.id', '=', 'e.user_id')
      ->leftJoin('wbcms_sites as site', 'site.id', '=', 'e.site_id')
      ->leftJoin('wbcms_acl_roles as role', 'role.id', '=', 'e.role_id')
      ->select('e.*', 'actor.name as actor_name', 'target.name as user_name', 'site.name as site_name', 'role.name as role_name')
      ->when($filters['history_user'] ?? '', fn ($query, $id) => $query->where(fn ($q) => $q->where('e.actor_id', $id)->orWhere('e.user_id', $id)))
      ->when($filters['history_site'] ?? '', fn ($query, $id) => $query->where('e.site_id', $id))
      ->when($filters['history_role'] ?? '', fn ($query, $id) => $query->where(fn ($q) => $q->where('e.role_id', $id)
        ->orWhere(fn ($q) => $q->where('e.action', 'assignment.changed')->where(fn ($q) => $q->where('e.before_value', (string) $id)->orWhere('e.after_value', (string) $id)))))
      ->when($filters['history_action'] ?? '', fn ($query, $action) => $query->where('e.action', $action))
      ->orderByDesc('e.id')->paginate(AdminPagination::perPage(), ['*'], 'history_page')->withQueryString();
  }

  public function historyOptions(array $filters): array
  {
    $users = DB::table('wbcms_acl_events')->select('actor_id as id')
      ->union(DB::table('wbcms_acl_events')->select('user_id as id'))->pluck('id');
    $sites = DB::table('wbcms_acl_events')->distinct()->pluck('site_id');
    $roles = DB::table('wbcms_acl_events')->distinct()->pluck('role_id')
      ->concat(DB::table('wbcms_acl_events')->where('action', 'assignment.changed')->distinct()->pluck('before_value'))
      ->concat(DB::table('wbcms_acl_events')->where('action', 'assignment.changed')->distinct()->pluck('after_value'));

    return [
      'user' => $this->referenceOptions('users', $users, $filters['history_user']),
      'site' => $this->referenceOptions('wbcms_sites', $sites, $filters['history_site']),
      'role' => $this->referenceOptions('wbcms_acl_roles', $roles, $filters['history_role']),
      'action' => collect(self::HISTORY_ACTIONS)->mapWithKeys(fn ($action) => [$action => __('webblocks-cms::acl.events.'.str_replace('.', '_', $action))])->all(),
    ];
  }

  private function referenceOptions(string $table, Collection $ids, mixed $selected): array
  {
    $ids = $ids->push($selected)->filter(fn ($id) => ctype_digit((string) $id) && (int) $id > 0)->map(fn ($id) => (int) $id)->unique();
    $names = DB::table($table)->whereIn('id', $ids)->pluck('name', 'id');

    return $ids->mapWithKeys(fn ($id) => [$id => isset($names[$id]) ? $names[$id].' (#'.$id.')' : __('webblocks-cms::acl.history_unavailable', ['id' => $id])])->sort(SORT_NATURAL | SORT_FLAG_CASE)->all();
  }
}
