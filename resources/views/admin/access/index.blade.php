@extends('webblocks-cms::layouts.admin', ['title' => __('webblocks-cms::acl.title'), 'heading' => __('webblocks-cms::acl.title')])
@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => __('webblocks-cms::acl.title'),
        'description' => __('webblocks-cms::acl.description'),
        'count' => $totalCount,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            @include('webblocks-cms::admin.partials.listing-filters', [
                'action' => route('admin.access.roles.index'),
                'search' => [
                    'id' => 'acl_roles_search', 'name' => 'q', 'label' => __('webblocks-cms::acl.search'),
                    'value' => $filters['q'], 'placeholder' => __('webblocks-cms::acl.search_placeholder'),
                ],
                'selects' => [[
                    'id' => 'acl_role_type', 'name' => 'type', 'label' => __('webblocks-cms::acl.role_type'),
                    'selected' => $filters['type'], 'placeholder' => __('webblocks-cms::acl.all_types'),
                    'options' => ['protected' => __('webblocks-cms::acl.built_in'), 'custom' => __('webblocks-cms::acl.custom')],
                ]],
                'showReset' => $filters['q'] !== '' || $filters['type'] !== '',
                'resetUrl' => route('admin.access.roles.index'),
                'applyLabel' => __('webblocks-cms::acl.apply'), 'resetLabel' => __('webblocks-cms::acl.reset'),
            ])
        </div>
    </div>

    <section class="wb-card" aria-labelledby="acl-roles-title">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2">
                <h2 class="wb-card-title" id="acl-roles-title">{{ __('webblocks-cms::acl.roles') }}</h2>
                <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $roles->total() }}</span>
            </div>
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <a class="wb-btn wb-btn-secondary" href="{{ route('admin.access.inspect') }}">{{ __('webblocks-cms::acl.inspect') }}</a>
                <a class="wb-btn wb-btn-primary" href="{{ route('admin.access.roles.create') }}">{{ __('webblocks-cms::acl.new') }}</a>
            </div>
        </div>
        <div class="wb-card-body">
            @if ($roles->isEmpty())
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ __('webblocks-cms::acl.roles_empty') }}</div>
                    <div class="wb-empty-text">{{ __('webblocks-cms::acl.roles_empty_help') }}</div>
                    <div class="wb-empty-action"><a class="wb-btn wb-btn-secondary" href="{{ route('admin.access.roles.index') }}">{{ __('webblocks-cms::acl.reset') }}</a></div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead><tr>
                            <th>{{ __('webblocks-cms::acl.name') }}</th>
                            <th>{{ __('webblocks-cms::acl.role_type') }}</th>
                            <th>{{ __('webblocks-cms::acl.assignments') }}</th>
                            <th class="wb-table-actions">{{ __('webblocks-cms::acl.actions') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($roles as $role)
                                <tr>
                                    <td>
                                        <div class="wb-stack wb-stack-1">
                                            @if ($role->is_protected)
                                                <strong>{{ $role->name }}</strong>
                                            @else
                                                <a href="{{ route('admin.access.roles.edit', $role) }}"><strong>{{ $role->name }}</strong></a>
                                            @endif
                                            @if ($role->description)<span class="wb-text-sm wb-text-muted">{{ $role->description }}</span>@endif
                                        </div>
                                    </td>
                                    <td><span class="wb-status-pill {{ $role->is_protected ? 'wb-status-info' : 'wb-status-active' }}">{{ $role->is_protected ? __('webblocks-cms::acl.built_in') : __('webblocks-cms::acl.custom') }}</span></td>
                                    <td class="wb-nowrap">{{ $role->assignment_count }}</td>
                                    <td class="wb-table-actions">
                                        <div class="wb-action-group">
                                            @if ($role->is_protected)
                                                <button type="button" class="wb-action-btn" data-wb-toggle="modal" data-wb-target="#acl-role-{{ $role->id }}" title="{{ __('webblocks-cms::acl.view_role') }}" aria-label="{{ __('webblocks-cms::acl.view_role') }}">
                                                    <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                                </button>
                                            @else
                                                <a class="wb-action-btn wb-action-btn-edit" href="{{ route('admin.access.roles.edit', $role) }}" title="{{ __('webblocks-cms::acl.edit') }}" aria-label="{{ __('webblocks-cms::acl.edit') }}"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                                            @endif
                                            <a class="wb-action-btn" href="{{ route('admin.access.roles.create', ['copy' => $role->id]) }}" title="{{ __('webblocks-cms::acl.copy') }}" aria-label="{{ __('webblocks-cms::acl.copy') }}"><i class="wb-icon wb-icon-copy" aria-hidden="true"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                @if ($role->is_protected)
                                    @push('overlays')
                                        @include('webblocks-cms::admin.access.partials.role-modal', ['rolePermissions' => $protectedRolePermissions[$role->id] ?? []])
                                    @endpush
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $roles, 'ariaLabel' => __('webblocks-cms::acl.roles_pagination')])
    </section>

    <section class="wb-card" aria-labelledby="acl-history-title">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-stack wb-stack-1">
                <h2 class="wb-card-title" id="acl-history-title">{{ __('webblocks-cms::acl.history') }}</h2>
                <span class="wb-text-sm wb-text-muted">{{ __('webblocks-cms::acl.history_description') }}</span>
            </div>
            <span class="wb-status-pill wb-status-info">{{ $history->total() }}</span>
        </div>
        <div class="wb-card-body">
            @if ($history->isEmpty())
                <div class="wb-empty wb-empty-sm">
                    <div class="wb-empty-title">{{ __('webblocks-cms::acl.history_empty') }}</div>
                    <div class="wb-empty-text">{{ __('webblocks-cms::acl.history_empty_help') }}</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead><tr>
                            <th>{{ __('webblocks-cms::acl.time') }}</th>
                            <th>{{ __('webblocks-cms::acl.actor') }}</th>
                            <th>{{ __('webblocks-cms::acl.action') }}</th>
                            <th>{{ __('webblocks-cms::acl.history_context') }}</th>
                            <th class="wb-table-actions">{{ __('webblocks-cms::acl.actions') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($history as $event)
                                @php
                                    $eventTime = \Carbon\CarbonImmutable::parse($event->created_at)->format('Y-m-d H:i');
                                    $eventAction = __('webblocks-cms::acl.events.'.str_replace('.', '_', $event->action));
                                    $eventActor = $event->actor_name ?? ($event->actor_id ? '#'.$event->actor_id : __('webblocks-cms::acl.unknown_actor'));
                                    $eventContext = collect([$event->role_name ?? ($event->action === 'role.deleted' ? $event->before_value : null), $event->user_name, $event->site_name])->filter()->implode(' · ');
                                @endphp
                                <tr>
                                    <td class="wb-nowrap wb-text-sm">{{ $eventTime }}</td>
                                    <td>{{ $eventActor }}</td>
                                    <td><div class="wb-stack wb-stack-1">
                                        <span>{{ $eventAction }}</span>
                                        @if ($event->permission)<span class="wb-text-sm wb-text-muted">{{ $permissions[$event->permission]['label'] ?? $event->permission }}</span>@endif
                                    </div></td>
                                    <td class="wb-text-sm">{{ $eventContext ?: __('webblocks-cms::acl.no_context') }}</td>
                                    <td class="wb-table-actions"><div class="wb-action-group">
                                        <button type="button" class="wb-action-btn" data-wb-toggle="modal" data-wb-target="#acl-event-{{ $event->id }}" title="{{ __('webblocks-cms::acl.history_view') }}" aria-label="{{ __('webblocks-cms::acl.history_view') }}"><i class="wb-icon wb-icon-eye" aria-hidden="true"></i></button>
                                    </div></td>
                                </tr>
                                @push('overlays')
                                    @include('webblocks-cms::admin.access.partials.event-modal')
                                @endpush
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $history, 'ariaLabel' => __('webblocks-cms::acl.history_pagination')])
    </section>
@endsection
