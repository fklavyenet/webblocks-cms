@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key, array $replace = []) => $adminTranslator->get('admin.users.'.$key, $adminLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('title'), 'heading' => $adminText('title')])

@section('content')
    @php
        $hasActiveFilters = $filters['q'] !== '' || $filters['status'] !== '' || $filters['role'] !== '';
    @endphp

    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $adminText('title'),
        'description' => $adminText('description'),
        'count' => $totalCount,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            @include('webblocks-cms::admin.partials.listing-filters', [
                'action' => route('admin.users.index'),
                'search' => [
                    'id' => 'users_search',
                    'name' => 'q',
                    'label' => $adminText('search'),
                    'value' => $filters['q'],
                    'placeholder' => $adminText('search_placeholder'),
                ],
                'selects' => [
                    [
                        'id' => 'users_role',
                        'name' => 'role',
                        'label' => $adminText('role'),
                        'selected' => $filters['role'],
                        'placeholder' => $adminText('all_roles'),
                        'options' => [
                            'super_admin' => $adminText('super_admins'),
                            'site_admin' => $adminText('site_admins'),
                            'editor' => $adminText('editors'),
                        ],
                    ],
                    [
                        'id' => 'users_status',
                        'name' => 'status',
                        'label' => $adminText('status'),
                        'selected' => $filters['status'],
                        'placeholder' => $adminText('all_statuses'),
                        'options' => [
                            'active' => $adminText('active'),
                            'inactive' => $adminText('inactive'),
                        ],
                    ],
                ],
                'showReset' => $hasActiveFilters,
                'resetUrl' => route('admin.users.index'),
                'applyLabel' => $adminText('apply'),
            ])
        </div>
    </div>

    @if ($users->isEmpty())
        <div class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>{{ $adminText('title') }}</strong>
                    <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
                </div>

                <a href="{{ route('admin.users.create') }}" class="wb-btn wb-btn-primary">{{ $adminText('add_user') }}</a>
            </div>

            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $adminText('empty_title') }}</div>
                    <div class="wb-empty-text">
                        {{ $hasActiveFilters ? $adminText('empty_filtered_help') : $adminText('empty_help') }}
                    </div>
                    <div class="wb-empty-action">
                        @if ($hasActiveFilters)
                            <a href="{{ route('admin.users.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('clear_filters') }}</a>
                        @endif
                        <a href="{{ route('admin.users.create') }}" class="wb-btn wb-btn-primary">{{ $adminText('add_user') }}</a>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>{{ $adminText('title') }}</strong>
                    <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
                </div>

                <a href="{{ route('admin.users.create') }}" class="wb-btn wb-btn-primary">{{ $adminText('add_user') }}</a>
            </div>

            <div class="wb-card-body">
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>{{ $adminText('name') }}</th>
                                <th>{{ $adminText('email') }}</th>
                                <th>{{ $adminText('role') }}</th>
                                <th>{{ $adminText('site_access') }}</th>
                                <th>{{ $adminText('status') }}</th>
                                <th>{{ $adminText('last_login') }}</th>
                                <th>{{ $adminText('actions') }}</th>
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
                                                    <span class="wb-text-sm wb-text-muted">{{ $adminText('you') }}</span>
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
                                    <td class="wb-table-actions">
                                        <div class="wb-action-group">
                                            <a href="{{ route('admin.users.edit', $managedUser) }}" class="wb-action-btn wb-action-btn-edit" title="{{ $adminText('edit_user') }}" aria-label="{{ $adminText('edit_user') }}">
                                                <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                            </a>

                                            <form method="POST" action="{{ route('admin.users.destroy', $managedUser) }}" onsubmit="return confirm('{{ $adminText('delete_confirm') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="wb-action-btn wb-action-btn-delete" title="{{ $deleteBlockedMessage ?: $adminText('delete_user') }}" aria-label="{{ $adminText('delete_user') }}" @disabled($deleteBlockedMessage !== null)>
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

            @include('webblocks-cms::admin.partials.pagination', ['paginator' => $users, 'ariaLabel' => $adminText('pagination'), 'compact' => true])
        </div>
    @endif
@endsection
