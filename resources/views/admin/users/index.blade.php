@extends('layouts.admin', ['title' => 'Users', 'heading' => 'Users'])

@section('content')
    @php
        $hasActiveFilters = $filters['q'] !== '' || $filters['status'] !== '' || $filters['role'] !== '';
    @endphp

    @include('admin.partials.page-header', [
        'title' => 'Users',
        'description' => 'Manage CMS users, admin access, and active account state without leaving the admin workspace.',
        'count' => $users->total(),
        'actions' => '<a href="'.route('admin.users.create').'" class="wb-btn wb-btn-primary">Add User</a>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            @include('admin.partials.listing-filters', [
                'action' => route('admin.users.index'),
                'search' => [
                    'id' => 'users_search',
                    'name' => 'q',
                    'label' => 'Search',
                    'value' => $filters['q'],
                    'placeholder' => 'Search by name or email',
                ],
                'selects' => [
                    [
                        'id' => 'users_status',
                        'name' => 'status',
                        'label' => 'Status',
                        'selected' => $filters['status'],
                        'placeholder' => 'All statuses',
                        'options' => [
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                        ],
                    ],
                    [
                        'id' => 'users_role',
                        'name' => 'role',
                        'label' => 'Role',
                        'selected' => $filters['role'],
                        'placeholder' => 'All roles',
                        'options' => [
                            'super_admin' => 'Super admins',
                            'site_admin' => 'Site admins',
                            'editor' => 'Editors',
                        ],
                    ],
                ],
                'showReset' => $hasActiveFilters,
                'resetUrl' => route('admin.users.index'),
                'applyLabel' => 'Apply',
            ])
        </div>
    </div>

    @if ($users->isEmpty())
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">No users found</div>
                    <div class="wb-empty-text">
                        {{ $hasActiveFilters ? 'No users match the current search or filters. Try broadening the results.' : 'Create the first managed user from this screen.' }}
                    </div>
                    <div class="wb-empty-action">
                        @if ($hasActiveFilters)
                            <a href="{{ route('admin.users.index') }}" class="wb-btn wb-btn-secondary">Clear Filters</a>
                        @endif
                        <a href="{{ route('admin.users.create') }}" class="wb-btn wb-btn-primary">Add User</a>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Site Access</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $managedUser)
                                @php($deleteBlockedMessage = $userLifecycleGuard->deletionBlocker($managedUser, auth()->user()))
                                <tr>
                                    <td>
                                        <div class="wb-stack wb-gap-1">
                                            <strong>{{ $managedUser->name }}</strong>
                                            <div class="wb-cluster wb-cluster-2 wb-text-sm">
                                                @if (auth()->id() === $managedUser->id)
                                                    <span class="wb-text-sm wb-text-muted">You</span>
                                                @endif
                                                @if ($deleteBlockedMessage)
                                                    <span class="wb-text-sm wb-text-muted">{{ $deleteBlockedMessage }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td><a href="mailto:{{ $managedUser->email }}" class="wb-link">{{ $managedUser->email }}</a></td>
                                    <td><span class="wb-status-pill {{ $managedUser->roleBadgeClass() }}">{{ $managedUser->roleLabel() }}</span></td>
                                    <td>{{ $managedUser->siteAccessSummary() }}</td>
                                    <td><span class="wb-status-pill {{ $managedUser->statusBadgeClass() }}">{{ $managedUser->statusLabel() }}</span></td>
                                    <td>{{ $managedUser->lastLoginLabel() }}</td>
                                    <td>{{ $managedUser->created_at?->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <div class="wb-action-group">
                                            <a href="{{ route('admin.users.edit', $managedUser) }}" class="wb-action-btn wb-action-btn-edit" title="Edit user" aria-label="Edit user">
                                                <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                            </a>

                                            <form method="POST" action="{{ route('admin.users.destroy', $managedUser) }}" onsubmit="return confirm('Delete this user?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="wb-action-btn wb-action-btn-delete" title="{{ $deleteBlockedMessage ?: 'Delete user' }}" aria-label="Delete user" @disabled($deleteBlockedMessage !== null)>
                                                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @include('admin.partials.pagination', ['paginator' => $users, 'ariaLabel' => 'Users pagination', 'compact' => true])
        </div>
    @endif
@endsection
