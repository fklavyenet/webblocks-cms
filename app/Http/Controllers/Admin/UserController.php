<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\User;
use App\Support\Users\UserLifecycleGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly UserLifecycleGuard $lifecycleGuard) {}

    public function index(): View
    {
        abort_unless(request()->user()?->can('manage-users'), 403);

        $users = User::query()
            ->orderByDesc('is_admin')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.users.index', [
            'users' => $users,
            'userLifecycleGuard' => $this->lifecycleGuard,
        ]);
    }

    public function create(): View
    {
        abort_unless(request()->user()?->can('manage-users'), 403);

        return view('admin.users.form', [
            'managedUser' => new User(['is_active' => true]),
            'pageTitle' => 'Add User',
            'formAction' => route('admin.users.store'),
            'formMethod' => 'POST',
        ]);
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $user = User::query()->create($request->validated());

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
            'updateBlockedMessage' => $this->lifecycleGuard->updateBlocker($user, old('is_admin', $user->is_admin), old('is_active', $user->is_active)),
        ]);
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $nextIsAdmin = (bool) $validated['is_admin'];
        $nextIsActive = (bool) $validated['is_active'];

        if ($message = $this->lifecycleGuard->updateBlocker($user, $nextIsAdmin, $nextIsActive)) {
            return back()->withInput()->withErrors(['user_lifecycle' => $message]);
        }

        if (($validated['password'] ?? null) === null || $validated['password'] === '') {
            unset($validated['password']);
        }

        $user->update(Arr::only($validated, ['name', 'email', 'password', 'is_admin', 'is_active']));

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
}
