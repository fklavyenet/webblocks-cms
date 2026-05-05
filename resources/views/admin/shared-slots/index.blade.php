@php
    $siteContext = $activeSite?->name ?? 'All sites';
    $siteContextDescription = $showAllSites
        ? 'Showing Shared Slots across all allowed sites.'
        : 'Showing Shared Slots for '.$activeSite->name.($activeSite?->domain ? ' ('.$activeSite->domain.')' : '').'.';
    $newSharedSlotUrl = $activeSite ? route('admin.shared-slots.create', ['site' => $activeSite->id]) : route('admin.shared-slots.create');
    $clearUrl = route('admin.shared-slots.index', $showAllSites ? ['site' => 'all'] : ['site' => $activeSite?->id]);
    $headerActions = '<form method="GET" action="'.route('admin.shared-slots.index').'" class="wb-inline-flex wb-items-center wb-gap-2 wb-flex-wrap">'
        .'<span class="wb-text-sm wb-text-muted wb-nowrap">Site</span>'
        .'<select id="shared_slots_site_context" name="site" class="wb-select wb-w-auto" aria-label="Site" onchange="this.form.submit()">'
        .collect($sites)->map(function ($site) use ($filters) {
            $selected = $filters['site'] === (string) $site->id ? ' selected' : '';

            return '<option value="'.$site->id.'"'.$selected.'>'.$site->name.'</option>';
        })->implode('')
        .'<option value="all"'.($filters['site'] === 'all' ? ' selected' : '').'>All sites</option>'
        .'</select>'
        .($canCreateSharedSlots ? '<a href="'.$newSharedSlotUrl.'" class="wb-btn wb-btn-primary">New Shared Slot</a>' : '')
        .'</form>';
@endphp

@extends('layouts.admin', ['title' => 'Shared Slots', 'heading' => 'Shared Slots'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Shared Slots',
        'context' => '<span>'.e($siteContextDescription).'</span>',
        'count' => $sharedSlots->total(),
        'actions' => $headerActions,
    ])

    @include('admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            @include('admin.partials.listing-filters', [
                'action' => route('admin.shared-slots.index'),
                'search' => [
                    'id' => 'shared_slots_search',
                    'name' => 'search',
                    'label' => 'Search',
                    'value' => $filters['search'],
                    'placeholder' => 'Search by name, handle, slot, or shell',
                ],
                'selects' => [
                    [
                        'id' => 'shared_slots_status',
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
                        'id' => 'shared_slots_sort',
                        'name' => 'sort',
                        'label' => 'Sort by',
                        'selected' => $filters['sort'],
                        'options' => [
                            'updated_at' => 'Updated at',
                            'name' => 'Name',
                            'handle' => 'Handle',
                            'slot_name' => 'Slot',
                            'public_shell' => 'Public shell',
                        ],
                    ],
                    [
                        'id' => 'shared_slots_direction',
                        'name' => 'direction',
                        'label' => 'Direction',
                        'selected' => $filters['direction'],
                        'options' => [
                            'desc' => 'Descending',
                            'asc' => 'Ascending',
                        ],
                    ],
                ],
                'hidden' => ['site' => $filters['site']],
                'showReset' => $filters['search'] !== '' || $filters['status'] !== '' || $filters['sort'] !== 'updated_at' || $filters['direction'] !== 'desc',
                'resetUrl' => $clearUrl,
                'applyLabel' => 'Apply',
            ])
        </div>
    </div>

    @if ($sharedSlots->isEmpty())
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">No Shared Slots found</div>
                    <div class="wb-empty-text">Create reusable Shared Slot content for {{ strtolower($siteContext) }}.</div>
                    @if ($canCreateSharedSlots)
                        <div class="wb-empty-action">
                            <a href="{{ $newSharedSlotUrl }}" class="wb-btn wb-btn-primary">Create Shared Slot</a>
                        </div>
                    @endif
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
                                <th>Handle</th>
                                <th>Site</th>
                                <th>Slot</th>
                                <th>Public Shell</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sharedSlots as $sharedSlot)
                                <tr>
                                    <td><strong>{{ $sharedSlot->name }}</strong></td>
                                    <td><code>{{ $sharedSlot->handle }}</code></td>
                                    <td>{{ $sharedSlot->site?->name }}</td>
                                    <td>{{ $sharedSlot->slotLabel() }}</td>
                                    <td>{{ $sharedSlot->publicShellLabel() }}</td>
                                    <td><span class="wb-status-pill {{ $sharedSlot->statusBadgeClass() }}">{{ $sharedSlot->statusLabel() }}</span></td>
                                    <td>{{ $sharedSlot->updated_at?->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <div class="wb-action-group">
                                            <a href="{{ route('admin.shared-slots.edit', $sharedSlot) }}" class="wb-action-btn wb-action-btn-edit" title="Edit Shared Slot" aria-label="Edit Shared Slot"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                                            <a href="{{ route('admin.shared-slots.blocks.edit', $sharedSlot) }}" class="wb-action-btn" title="Edit Shared Slot blocks" aria-label="Edit Shared Slot blocks"><i class="wb-icon wb-icon-layout-panel-top" aria-hidden="true"></i></a>
                                            <form method="POST" action="{{ route('admin.shared-slots.destroy', $sharedSlot) }}" onsubmit="return confirm('Delete this Shared Slot?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete Shared Slot" aria-label="Delete Shared Slot"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @include('admin.partials.pagination', ['paginator' => $sharedSlots, 'ariaLabel' => 'Shared Slots pagination', 'compact' => true])
        </div>
    @endif
@endsection
