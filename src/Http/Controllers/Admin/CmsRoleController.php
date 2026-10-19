<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Actions\Access\WriteCmsAccess;
use WebBlocks\Cms\Http\Requests\Admin\CmsRoleIndexRequest;
use WebBlocks\Cms\Http\Requests\Admin\CmsRoleRequest;
use WebBlocks\Cms\Http\Requests\Admin\DeleteCmsRoleRequest;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Policies\CmsRolePolicy;
use WebBlocks\Cms\Queries\Access\CmsRoleQuery;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Access\PermissionCatalog;
use WebBlocks\Cms\Support\Admin\AdminPagination;

class CmsRoleController extends Controller
{
  public function index(CmsRoleIndexRequest $request, CmsRoleQuery $query)
  {
    $filters = ['q' => trim((string) $request->validated('q', '')), 'type' => $request->validated('type', '') ?? ''];
    $roles = $query->listing($filters);
    $history = $query->history();
    AdminPagination::redirectOutOfRange($roles, $request, 'roles_page');
    AdminPagination::redirectOutOfRange($history, $request, 'history_page');

    return view('webblocks-cms::admin.access.index', [
      'roles' => $roles, 'history' => $history, 'filters' => $filters, 'totalCount' => $query->total(),
      'permissions' => app(PermissionCatalog::class)->editable(),
      'protectedRolePermissions' => $roles->getCollection()->where('is_protected', true)->mapWithKeys(fn (CmsRole $role) => [$role->id => $query->permissions($role)]),
    ]);
  }

  public function create(Request $request, CmsRoleQuery $query)
  {
    $this->authorize();
    $source = $request->filled('copy') ? CmsRole::query()->findOrFail($request->integer('copy')) : null;

    return $this->form(new CmsRole, $source ? $query->permissions($source) : [], $source);
  }

  public function edit(CmsRole $cmsRole, CmsRoleQuery $query)
  {
    $this->authorize();

    return $this->form($cmsRole, $query->permissions($cmsRole));
  }

  private function form(CmsRole $role, array $selected, ?CmsRole $source = null)
  {
    return view('webblocks-cms::admin.access.form', ['role' => $role, 'selected' => $selected, 'source' => $source, 'permissions' => app(PermissionCatalog::class)->editable(), 'assignments' => $role->exists ? DB::table('wbcms_site_user as m')->join('users as u', 'u.id', '=', 'm.user_id')->join('wbcms_sites as s', 's.id', '=', 'm.site_id')->where('m.role_id', $role->id)->select('u.name as user_name', 's.name as site_name')->get() : collect()]);
  }

  public function store(CmsRoleRequest $request, WriteCmsAccess $write)
  {
    $role = $write->role($request->user(), $request->validated());

    return redirect()->route('admin.access.roles.edit', $role)->with('status', __('webblocks-cms::acl.saved'));
  }

  public function update(CmsRoleRequest $request, CmsRole $cmsRole, WriteCmsAccess $write)
  {
    $write->role($request->user(), $request->validated(), $cmsRole);

    return back()->with('status', __('webblocks-cms::acl.saved'));
  }

  public function destroy(DeleteCmsRoleRequest $request, CmsRole $cmsRole, WriteCmsAccess $write)
  {
    $this->authorize();
    $data = $request->validated();
    $write->deleteRole($request->user(), $cmsRole, $data['version']);

    return redirect()->route('admin.access.roles.index')->with('status', __('webblocks-cms::acl.deleted'));
  }

  public function inspect(Request $request, CmsAuthorization $acl)
  {
    $this->authorize();
    $user = $request->filled('user_id') ? User::query()->findOrFail($request->integer('user_id')) : null;
    $site = $request->filled('site_id') ? Site::query()->findOrFail($request->integer('site_id')) : null;
    $permissions = app(PermissionCatalog::class)->editable();
    $effective = [];
    if ($user && $site) {
      foreach ($permissions as $key => $definition) {
      $effective[$key] = $acl->allows($user, $key, $site->id);
      }
    }

    return view('webblocks-cms::admin.access.inspect', ['users' => User::query()->cmsUsers()->orderBy('name')->get(), 'sites' => Site::query()->orderBy('name')->get(), 'selectedUser' => $user, 'selectedSite' => $site, 'permissions' => $permissions, 'effective' => $effective, 'assignedRole' => $user && $site ? CmsRole::query()->find($acl->roleId($user, $site->id)) : null]);
  }

  private function authorize(): void
  {
    abort_unless(app(CmsRolePolicy::class)->manage(request()->user()), 403);
  }
}
