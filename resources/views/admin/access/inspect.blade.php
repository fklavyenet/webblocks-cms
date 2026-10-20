@extends('webblocks-cms::layouts.admin', ['title' => __('webblocks-cms::acl.inspect'), 'heading' => __('webblocks-cms::acl.inspect')])
@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => __('webblocks-cms::acl.inspect'), 'description' => __('webblocks-cms::acl.inspect_help'),
    ])

    @php($resetUrl = route('admin.access.inspect', array_filter(['user_id' => $selectedUser?->id, 'site_id' => $selectedSite?->id])))
    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            @include('webblocks-cms::admin.partials.listing-filters', [
                'action' => route('admin.access.inspect'),
                'search' => [
                    'id' => 'acl_permission_search', 'name' => 'q', 'label' => __('webblocks-cms::acl.search'),
                    'value' => $filters['q'], 'placeholder' => __('webblocks-cms::acl.permission_search_placeholder'),
                ],
                'selects' => [
                    ['id' => 'acl_user', 'name' => 'user_id', 'label' => __('webblocks-cms::acl.user'), 'selected' => $selectedUser?->id,
                     'placeholder' => __('webblocks-cms::acl.select_user'), 'options' => $users->pluck('name', 'id')->all()],
                    ['id' => 'acl_site', 'name' => 'site_id', 'label' => __('webblocks-cms::acl.site'), 'selected' => $selectedSite?->id,
                     'placeholder' => __('webblocks-cms::acl.select_site'), 'options' => $sites->pluck('name', 'id')->all()],
                    ['id' => 'acl_permission_status', 'name' => 'status', 'label' => __('webblocks-cms::acl.permission_status'), 'selected' => $filters['status'],
                     'placeholder' => __('webblocks-cms::acl.all_permissions'),
                     'options' => ['allowed' => __('webblocks-cms::acl.granted_filter'), 'denied' => __('webblocks-cms::acl.denied_filter')]],
                ],
                'showReset' => $filters['q'] !== '' || $filters['status'] !== '', 'resetUrl' => $resetUrl,
                'applyLabel' => __('webblocks-cms::acl.show'), 'resetLabel' => __('webblocks-cms::acl.reset'),
            ])
        </div>
    </div>

    <section class="wb-card" aria-labelledby="acl-permissions-title">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-stack wb-stack-1">
                <h2 class="wb-card-title" id="acl-permissions-title">{{ __('webblocks-cms::acl.permissions_title') }}</h2>
                @if ($selectedUser && $selectedSite)
                    <span>{{ $selectedUser->name }} · {{ $selectedSite->name }}</span>
                    <span class="wb-text-sm wb-text-muted">{{ $subjectIsSuperAdmin ? __('webblocks-cms::acl.super_admin') : ($assignedRole?->name ?? __('webblocks-cms::acl.none')) }}</span>
                @endif
            </div>
            @if ($selectedUser && $selectedSite)
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <span class="wb-status-pill wb-status-info">{{ __('webblocks-cms::acl.permissions_matched', ['shown' => $filteredCount, 'total' => $totalCount]) }}</span>
                    <span class="wb-status-pill wb-status-active">{{ __('webblocks-cms::acl.permissions_granted', ['count' => $grantedCount]) }}</span>
                    <span class="wb-status-pill wb-status-inactive">{{ __('webblocks-cms::acl.permissions_denied', ['count' => $deniedCount]) }}</span>
                </div>
            @endif
        </div>
        <div class="wb-card-body">
            @if (! $selectedUser || ! $selectedSite)
                <div class="wb-empty wb-empty-sm"><div class="wb-empty-title">{{ __('webblocks-cms::acl.inspect_prompt') }}</div></div>
            @elseif ($permissionGroups->isEmpty())
                <div class="wb-empty wb-empty-sm">
                    <div class="wb-empty-title">{{ __('webblocks-cms::acl.inspect_empty') }}</div>
                    <div class="wb-empty-text">{{ __('webblocks-cms::acl.inspect_empty_help') }}</div>
                    <div class="wb-empty-action"><a class="wb-btn wb-btn-secondary" href="{{ $resetUrl }}">{{ __('webblocks-cms::acl.reset') }}</a></div>
                </div>
            @else
                <div class="wb-api-token-capability-groups wb-items-start">
                    @foreach ($permissionGroups as $group => $items)
                        <details class="wb-api-token-capability-group" @if ($filters['q'] !== '' || $filters['status'] !== '') open @endif>
                            <summary class="wb-api-token-capability-summary" id="acl-inspect-group-{{ $loop->index }}">
                                <span class="wb-api-token-capability-summary-copy"><strong>{{ __('webblocks-cms::acl.groups.'.$group) }}</strong></span>
                                <span class="wb-status-pill wb-status-info">{{ $items->count() }}</span>
                            </summary>
                            <div class="wb-api-token-capability-list" aria-labelledby="acl-inspect-group-{{ $loop->index }}">
                                @foreach ($items as $key => $definition)
                                    <div class="wb-api-token-capability-option wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap" data-acl-permission="{{ $key }}" data-acl-granted="{{ $definition['allowed'] ? 'true' : 'false' }}">
                                        <span class="wb-api-token-capability-copy"><strong>{{ $key }}</strong><span class="wb-text-sm wb-text-muted">{{ $definition['label'] }}</span></span>
                                        <span class="wb-status-pill {{ $definition['allowed'] ? 'wb-status-active' : 'wb-status-inactive' }}" title="{{ __('webblocks-cms::acl.'.($definition['allowed'] ? 'allowed' : 'denied')) }}">{{ __('webblocks-cms::acl.'.($definition['allowed'] ? 'granted_filter' : 'denied_filter')) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="wb-card-footer"><a class="wb-btn wb-btn-secondary" href="{{ route('admin.access.roles.index') }}">{{ __('webblocks-cms::acl.back_to_roles') }}</a></div>
    </section>
@endsection
