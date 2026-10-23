<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use WebBlocks\Cms\Actions\Access\WriteCmsAccess;
use WebBlocks\Cms\Http\Requests\Admin\BulkDeleteUsersRequest;
use WebBlocks\Cms\Http\Requests\Admin\UserStoreRequest;
use WebBlocks\Cms\Http\Requests\Admin\UserUpdateRequest;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Queries\AdminTableSort;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Admin\AdminPagination;
use WebBlocks\Cms\Support\Users\UserLifecycleGuard;

class UserController extends Controller
{
  public function __construct(private readonly UserLifecycleGuard $lifecycleGuard) {}

  public function index(Request $request): View
  {
    abort_unless($request->user()?->can('manage-users'), 403);

    $filters = [
      'q' => trim((string) $request->string('q')),
      'status' => $this->normalizedStatusFilter((string) $request->string('status')),
      'role' => $this->normalizedRoleFilter((string) $request->string('role')),
    ];

    $totalCount = User::query()->cmsUsers()->count();

    $users = $this->filteredUsersQuery($filters)
      ->with('sites')
      ->withRoleOrder()
      ->orderBy('name')
      ->tap(fn ($query) => AdminTableSort::for('users')->apply($query))
      ->paginate(AdminPagination::perPage())
      ->withQueryString();

    AdminPagination::redirectOutOfRange($users, $request);

    return view('webblocks-cms::admin.users.index', [
      'users' => $users,
      'filters' => $filters,
      'userLifecycleGuard' => $this->lifecycleGuard,
      'totalCount' => $totalCount,
      'filteredCount' => $users->total(),
    ]);
  }

  public function create(): View
  {
    abort_unless(request()->user()?->can('manage-users'), 403);

    return view('webblocks-cms::admin.users.form', [
      'managedUser' => new User(['is_active' => true, 'role' => User::ROLE_EDITOR]),
      'pageTitle' => 'Add User',
      'formAction' => route('admin.users.store'),
      'formMethod' => 'POST',
      'sites' => Site::query()->primaryFirst()->orderBy('name')->get(),
      'cmsRoles' => app(CmsAuthorization::class)->ready() ? CmsRole::query()->orderBy('name')->get() : collect(),
      'siteRoles' => [],
      'assignedSiteIds' => [],
    ]);
  }

  public function store(UserStoreRequest $request): RedirectResponse
  {
    $validated = $request->validated();
    $user = app(WriteCmsAccess::class)->user($request->user(), new User, $validated);

    return redirect()->route('admin.users.edit', $user)->with('status', 'User created successfully.');
  }

  public function edit(User $user): View
  {
    abort_unless(request()->user()?->can('manage-users'), 403);
    abort_unless($this->isCmsManagedUser($user), 404);

    return view('webblocks-cms::admin.users.form', [
      'managedUser' => $user,
      'pageTitle' => 'Edit User: '.$user->name,
      'formAction' => route('admin.users.update', $user),
      'formMethod' => 'PUT',
      'deleteBlockedMessage' => $this->lifecycleGuard->deletionBlocker($user, request()->user()),
      'updateBlockedMessage' => $this->lifecycleGuard->updateBlocker($user, (string) old('role', $user->normalizedRole()), (bool) old('is_active', $user->is_active)),
      'sites' => Site::query()->primaryFirst()->orderBy('name')->get(),
      'cmsRoles' => app(CmsAuthorization::class)->ready() ? CmsRole::query()->orderBy('name')->get() : collect(),
      'siteRoles' => isset($user) ? DB::table('wbcms_site_user')->where('user_id', $user->id)->pluck('role_id', 'site_id')->all() : [],
      'assignedSiteIds' => $user->sites()->pluck('wbcms_sites.id')->all(),
    ]);
  }

  public function update(UserUpdateRequest $request, User $user): RedirectResponse
  {
    abort_unless($this->isCmsManagedUser($user), 404);

    $validated = $request->validated();
    $siteIds = $validated['site_ids'] ?? [];

    $nextRole = (string) $validated['role'];
    $nextIsActive = (bool) $validated['is_active'];

    if ($message = $this->lifecycleGuard->updateBlocker($user, $nextRole, $nextIsActive)) {
      return back()->withInput()->withErrors(['user_lifecycle' => $message]);
    }

    if (($validated['password'] ?? null) === null || $validated['password'] === '') {
      unset($validated['password']);
    }

    $user = app(WriteCmsAccess::class)->user($request->user(), $user, $validated);

    return redirect()->route('admin.users.edit', $user)->with('status', 'User updated successfully.');
  }

  public function destroy(User $user): RedirectResponse
  {
    abort_unless(request()->user()?->can('manage-users'), 403);
    abort_unless($this->isCmsManagedUser($user), 404);

    if ($message = $this->lifecycleGuard->deletionBlocker($user, request()->user())) {
      return redirect()->route('admin.users.index')->withErrors(['user_lifecycle' => $message]);
    }

    app(WriteCmsAccess::class)->deleteUser(request()->user(), $user);

    return redirect()->route('admin.users.index')->with('status', 'User deleted successfully.');
  }

  public function bulkDestroy(BulkDeleteUsersRequest $request): RedirectResponse
  {
    $ids = collect($request->validated('user_ids'))
      ->map(fn (mixed $id): int => (int) $id)
      ->unique()
      ->values();

    $users = User::query()
      ->cmsUsers()
      ->whereKey($ids->all())
      ->get()
      ->keyBy(fn (User $user): int => (int) $user->getKey());

    $deleted = 0;
    $failed = 0;

    foreach ($ids as $id) {
      $user = $users->get($id);

      if (! $user || $this->lifecycleGuard->deletionBlocker($user, $request->user()) !== null) {
        $failed++;

        continue;
      }

      app(WriteCmsAccess::class)->deleteUser(request()->user(), $user);
      $deleted++;
    }

    $message = $this->bulkDeleteMessage($deleted, $failed);
    $redirect = redirect()
      ->route('admin.users.index')
      ->with($deleted > 0 ? 'status' : 'bulk_status', $message);

    if ($failed > 0) {
      $redirect->withErrors(['users' => 'Some selected users could not be deleted because they are protected or no longer managed by CMS.']);
    }

    return $redirect;
  }

  private function filteredUsersQuery(array $filters): Builder
  {
    return User::query()
      ->cmsUsers()
      ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
        $term = '%'.$filters['q'].'%';

        $query->where(function (Builder $subquery) use ($term): void {
          $subquery
            ->where('name', 'like', $term)
            ->orWhere('email', 'like', $term);
        });
      })
      ->when($filters['status'] === 'active', fn (Builder $query) => $query->where('is_active', true))
      ->when($filters['status'] === 'inactive', fn (Builder $query) => $query->where('is_active', false))
      ->when($filters['role'] !== '', fn (Builder $query) => $query->where('role', $filters['role']));
  }

  private function normalizedStatusFilter(string $value): string
  {
    return in_array($value, ['active', 'inactive'], true) ? $value : '';
  }

  private function normalizedRoleFilter(string $value): string
  {
    return in_array($value, User::roles(), true) ? $value : '';
  }

  private function isCmsManagedUser(User $user): bool
  {
    return User::query()
      ->whereKey($user->getKey())
      ->cmsUsers()
      ->exists();
  }

  private function bulkDeleteMessage(int $deleted, int $failed): string
  {
    $message = $deleted === 1
      ? '1 selected user deleted.'
      : $deleted.' selected users deleted.';

    if ($failed > 0) {
      $message .= ' '.$failed.' could not be deleted.';
    }

    return $message;
  }
}
