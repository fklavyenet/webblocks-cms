<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\Site;
use App\Models\User;
use App\Support\Users\UserLifecycleGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

        $users = $this->filteredUsersQuery($filters)
            ->with('sites')
            ->withRoleOrder()
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'userLifecycleGuard' => $this->lifecycleGuard,
        ]);
    }

    public function create(): View
    {
        abort_unless(request()->user()?->can('manage-users'), 403);

        return view('admin.users.form', [
            'managedUser' => new User(['is_active' => true, 'role' => User::ROLE_EDITOR]),
            'pageTitle' => 'Add User',
            'formAction' => route('admin.users.store'),
            'formMethod' => 'POST',
            'sites' => Site::query()->primaryFirst()->orderBy('name')->get(),
        ]);
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $siteIds = $validated['site_ids'] ?? [];
        unset($validated['site_ids']);

        $user = User::query()->create($validated);
        $user->sites()->sync($user->isSuperAdmin() ? [] : $siteIds);

        return redirect()->route('admin.users.edit', $user)->with('status', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        abort_unless(request()->user()?->can('manage-users'), 403);

        return view('admin.users.form', [
            'managedUser' => $user,
            'pageTitle' => 'Edit User: '.$user->name,
            'formAction' => route('admin.users.update', $user),
            'formMethod' => 'PUT',
            'deleteBlockedMessage' => $this->lifecycleGuard->deletionBlocker($user, request()->user()),
            'updateBlockedMessage' => $this->lifecycleGuard->updateBlocker($user, (string) old('role', $user->normalizedRole()), (bool) old('is_active', $user->is_active)),
            'sites' => Site::query()->primaryFirst()->orderBy('name')->get(),
        ]);
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $siteIds = $validated['site_ids'] ?? [];
        unset($validated['site_ids']);
        $nextRole = (string) $validated['role'];
        $nextIsActive = (bool) $validated['is_active'];

        if ($message = $this->lifecycleGuard->updateBlocker($user, $nextRole, $nextIsActive)) {
            return back()->withInput()->withErrors(['user_lifecycle' => $message]);
        }

        if (($validated['password'] ?? null) === null || $validated['password'] === '') {
            unset($validated['password']);
        }

        $user->update(Arr::only($validated, ['name', 'email', 'password', 'role', 'is_active']));
        $user->sites()->sync($user->isSuperAdmin() ? [] : $siteIds);

        return redirect()->route('admin.users.edit', $user)->with('status', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_unless(request()->user()?->can('manage-users'), 403);

        if ($message = $this->lifecycleGuard->deletionBlocker($user, request()->user())) {
            return redirect()->route('admin.users.index')->withErrors(['user_lifecycle' => $message]);
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User deleted successfully.');
    }

    private function filteredUsersQuery(array $filters): Builder
    {
        return User::query()
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
}
