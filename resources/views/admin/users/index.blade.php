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
        <div class="wb-card" data-wb-admin-bulk-listing>
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>{{ $adminText('title') }}</strong>
                    <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
                </div>

                <a href="{{ route('admin.users.create') }}" class="wb-btn wb-btn-primary">{{ $adminText('add_user') }}</a>
            </div>

            <div class="wb-card-body">
                @include('webblocks-cms::admin.partials.listing-bulk-actions', [
                    'label' => $adminText('selected'),
                    'deleteTarget' => '#bulk-delete-users-modal',
                    'deleteLabel' => $adminText('delete_selected'),
                ])

                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>
                                    <label class="wb-checkbox" for="select_all_visible_users">
                                        <input id="select_all_visible_users" type="checkbox" data-wb-admin-select-all-visible aria-label="{{ $adminText('select_all_visible') }}">
                                        <span class="wb-sr-only">{{ $adminText('select_all_visible') }}</span>
                                    </label>
                                </th>
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
                                        <label class="wb-checkbox" for="user_select_{{ $managedUser->id }}">
                                            <input
                                                id="user_select_{{ $managedUser->id }}"
                                                type="checkbox"
                                                value="{{ $managedUser->id }}"
                                                @if ($deleteBlockedMessage === null) data-wb-admin-row-select @endif
                                                aria-label="{{ $adminText('select_user', ['name' => $managedUser->name]) }}"
                                                @disabled($deleteBlockedMessage !== null)
                                            >
                                            <span class="wb-sr-only">{{ $adminText('select_user', ['name' => $managedUser->name]) }}</span>
                                        </label>
                                    </td>
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

                                            <button
                                                type="button"
                                                class="wb-action-btn wb-action-btn-delete"
                                                title="{{ $deleteBlockedMessage ?: $adminText('delete_user') }}"
                                                aria-label="{{ $adminText('delete_user') }}"
                                                data-wb-toggle="modal"
                                                data-wb-target="#delete-user-{{ $managedUser->id }}-modal"
                                                @disabled($deleteBlockedMessage !== null)
                                            >
                                                <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                @push('overlays')
                                    @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                                        'id' => 'delete-user-'.$managedUser->id.'-modal',
                                        'title' => $adminText('delete_user_title'),
                                        'description' => $adminText('delete_user_description'),
                                        'action' => route('admin.users.destroy', $managedUser),
                                        'method' => 'DELETE',
                                        'submitLabel' => $adminText('delete_user'),
                                    ])
                                        <div class="wb-card wb-card-muted">
                                            <div class="wb-card-body wb-stack wb-gap-2">
                                                <div><strong>{{ $managedUser->name }}</strong></div>
                                                <div class="wb-text-sm wb-text-muted">{{ $managedUser->email }}</div>
                                                <div class="wb-text-sm wb-text-muted">{{ $adminText('role') }} {{ $managedUser->roleLabel() }} | {{ $adminText('site_access') }} {{ $managedUser->siteAccessSummary() }}</div>
                                            </div>
                                        </div>

                                        <p class="wb-text-sm wb-text-muted">{{ $adminText('delete_user_warning') }}</p>
                                    @endcomponent
                                @endpush
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @include('webblocks-cms::admin.partials.pagination', ['paginator' => $users, 'ariaLabel' => $adminText('pagination'), 'compact' => true])
        </div>

        @push('overlays')
            @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                'id' => 'bulk-delete-users-modal',
                'title' => $adminText('bulk_delete_title'),
                'description' => $adminText('bulk_delete_description'),
                'action' => route('admin.users.bulk-destroy'),
                'method' => 'DELETE',
                'submitLabel' => $adminText('delete_selected'),
                'formAttributes' => [
                    'data-wb-admin-bulk-delete-form' => true,
                    'data-wb-admin-bulk-input-name' => 'user_ids[]',
                ],
                'submitAttributes' => [
                    'data-wb-admin-bulk-delete-submit' => true,
                    'disabled' => true,
                ],
            ])
                <div class="wb-card wb-card-muted">
                    <div class="wb-card-body wb-stack wb-gap-2">
                        <strong><span data-wb-admin-bulk-modal-count>0</span> {{ $adminText('bulk_delete_count_suffix') }}</strong>
                        <p class="wb-text-sm wb-text-muted">{{ $adminText('bulk_delete_help') }}</p>
                    </div>
                </div>

                <div data-wb-admin-bulk-inputs></div>
                <input type="hidden" name="user_ids[]" value="" disabled data-wb-admin-bulk-empty-input>
            @endcomponent
        @endpush

        @push('scripts')
            @php($bulkActionsJsPath = public_path('cms/js/admin/listing-bulk-actions.js'))
            @if (is_file($bulkActionsJsPath))
                <script src="{{ asset('cms/js/admin/listing-bulk-actions.js') }}?v={{ filemtime($bulkActionsJsPath) }}" defer></script>
            @endif
        @endpush
    @endif
@endsection
