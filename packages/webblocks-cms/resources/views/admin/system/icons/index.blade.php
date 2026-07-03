@extends('webblocks-cms::layouts.admin', ['title' => 'Icons', 'heading' => 'Icons'])

@php
    $hasActiveFilters = $filters['search'] !== '' || $filters['source'] !== '' || $filters['tag'] !== '' || $filters['status'] !== '';
    $requestedModal = $requestedModal ?? null;
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Icons',
        'description' => 'Manage the install-level icon catalog used by admin pickers. WebBlocks UI provides the CSS classes and manifest; CMS stores labels, contexts, activity, and sort order.',
        'count' => $totalCount,
        'actions' => '<form method="POST" action="'.e(route('admin.system.icons.sync-webblocks-ui')).'">'.csrf_field().'<button type="submit" class="wb-btn wb-btn-primary">Sync Manifest</button></form><a href="'.e($defaultManifest).'" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">Open Manifest</a>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            @include('webblocks-cms::admin.partials.listing-filters', [
                'action' => route('admin.system.icons.index'),
                'search' => [
                    'id' => 'icons_search',
                    'name' => 'search',
                    'label' => 'Search',
                    'value' => $filters['search'],
                    'placeholder' => 'Search icons...',
                ],
                'selects' => [
                    [
                        'id' => 'icons_source',
                        'name' => 'source',
                        'label' => 'Source',
                        'value' => $filters['source'],
                        'placeholder' => 'All sources',
                        'options' => collect($sources)->mapWithKeys(fn (string $source) => [$source => $source])->all(),
                    ],
                    [
                        'id' => 'icons_tag',
                        'name' => 'tag',
                        'label' => 'Context',
                        'value' => $filters['tag'],
                        'placeholder' => 'All contexts',
                        'options' => $tags,
                    ],
                    [
                        'id' => 'icons_status',
                        'name' => 'status',
                        'label' => 'Status',
                        'value' => $filters['status'],
                        'placeholder' => 'All statuses',
                        'options' => ['active' => 'Active', 'inactive' => 'Inactive'],
                    ],
                ],
                'showReset' => $hasActiveFilters,
                'resetUrl' => route('admin.system.icons.index'),
                'applyLabel' => 'Apply filters',
            ])
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <strong>Icons</strong>
                <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
            </div>
        </div>
        @if ($icons->isEmpty())
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">No icons found</div>
                    <div class="wb-empty-text">Run the WebBlocks UI icon sync command or seed the fallback catalog.</div>
                </div>
            </div>
        @else
            <div class="wb-card-body">
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>Icon</th>
                                <th>Label</th>
                                <th>Source</th>
                                <th>Contexts</th>
                                <th>Status</th>
                                <th>Sort</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($icons as $icon)
                                <tr>
                                    <td class="wb-nowrap">
                                        <span class="wb-cluster wb-cluster-2">
                                            <i class="wb-icon {{ $icon->css_class }}" aria-hidden="true"></i>
                                            <code>{{ $icon->css_class }}</code>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="wb-stack wb-gap-1">
                                            <strong>{{ $icon->label }}</strong>
                                            <span class="wb-text-sm wb-text-muted"><code>{{ $icon->slug }}</code></span>
                                        </div>
                                    </td>
                                    <td>{{ $icon->source }}</td>
                                    <td>
                                        <div class="wb-cluster wb-cluster-2 wb-text-sm">
                                            @foreach (array_values(array_unique(array_merge($icon->contexts ?? [], $icon->categories ?? []))) as $tag)
                                                <span class="wb-status-pill wb-status-pending">{{ $tag }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        <span class="wb-status-pill {{ $icon->is_active ? 'wb-status-active' : 'wb-status-danger' }}">{{ $icon->is_active ? 'Active' : 'Inactive' }}</span>
                                    </td>
                                    <td>{{ $icon->sort_order }}</td>
                                    <td>
                                        <div class="wb-action-group">
                                            <a href="{{ route('admin.system.icons.index', array_filter(array_merge(request()->query(), ['modal' => 'edit-icon', 'icon' => $icon->id]))) }}" class="wb-action-btn wb-action-btn-edit" title="Edit icon" aria-label="Edit icon" aria-haspopup="dialog" aria-controls="iconEditModal-{{ $icon->id }}">
                                                <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $icons, 'ariaLabel' => 'Icons pagination', 'compact' => true])
    </div>
@endsection

@push('overlays')
    @if ($requestedModal === 'edit-icon' && $editIcon)
        @include('webblocks-cms::admin.system.icons.partials.edit-modal', [
            'icon' => $editIcon,
            'closeUrl' => $closeUrl,
        ])
    @endif
@endpush
