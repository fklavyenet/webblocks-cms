@extends('layouts.admin', ['title' => 'Users', 'heading' => 'Users'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Users',
        'description' => 'Manage CMS users, admin access, and active account state without leaving the admin workspace.',
        'count' => $users->total(),
        'actions' => '<a href="'.route('admin.users.create').'" class="wb-btn wb-btn-primary">Add User</a>',
    ])

    @include('admin.partials.flash')

    @if ($users->isEmpty())
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">No users found</div>
                    <div class="wb-empty-text">Create the first managed user from this screen.</div>
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
                                            @if (auth()->id() === $managedUser->id)
                                                <span class="wb-text-sm wb-text-muted">You</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td><a href="mailto:{{ $managedUser->email }}" class="wb-link">{{ $managedUser->email }}</a></td>
                                    <td><span class="wb-status-pill {{ $managedUser->roleBadgeClass() }}">{{ $managedUser->roleLabel() }}</span></td>
                                    <td><span class="wb-status-pill {{ $managedUser->statusBadgeClass() }}">{{ $managedUser->statusLabel() }}</span></td>
                                    <td>{{ $managedUser->last_login_at?->format('Y-m-d H:i') ?? 'Never' }}</td>
                                    <td>{{ $managedUser->created_at?->format('Y-m-d') }}</td>
                                    <td>
                                        <div class="wb-action-group">
                                            <a href="{{ route('admin.users.edit', $managedUser) }}" class="wb-action-btn wb-action-btn-edit" title="Edit user" aria-label="Edit user">
                                                <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                            </a>

                                            <form method="POST" action="{{ route('admin.users.destroy', $managedUser) }}" onsubmit="return confirm('Delete this user?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete user" aria-label="Delete user" @disabled($deleteBlockedMessage !== null)>
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

            @include('admin.partials.pagination', ['paginator' => $users])
        </div>
    @endif
@endsection
