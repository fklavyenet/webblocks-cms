@extends('layouts.admin', ['title' => 'Page Layouts', 'heading' => 'Page Layouts'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Page Layouts',
        'description' => 'Manage reusable public page layout definitions. Pages still store the selected layout handle on public_shell for backward compatibility.',
        'count' => $totalCount,
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <strong>Page Layouts</strong>
                <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
            </div>

            <a href="{{ route('admin.page-layouts.create') }}" class="wb-btn wb-btn-primary">New Page Layout</a>
        </div>

        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Handle</th>
                            <th>Body Class</th>
                            <th>Status</th>
                            <th>Ownership</th>
                            <th>Sort Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pageLayouts as $pageLayout)
                            <tr>
                                <td>
                                    <div class="wb-stack wb-stack-1">
                                        <strong>{{ $pageLayout->name }}</strong>
                                        <span class="wb-text-sm wb-text-muted">{{ $pageLayout->description ?: '-' }}</span>
                                    </div>
                                </td>
                                <td class="wb-nowrap"><code>{{ $pageLayout->handle }}</code></td>
                                <td class="wb-nowrap"><code>{{ $pageLayout->body_class ?: '-' }}</code></td>
                                <td><span class="wb-status-pill {{ $pageLayout->statusBadgeClass() }}">{{ $pageLayout->statusLabel() }}</span></td>
                                <td><span class="wb-status-pill {{ $pageLayout->is_system ? 'wb-status-info' : 'wb-status-pending' }}">{{ strtolower($pageLayout->ownershipLabel()) }}</span></td>
                                <td class="wb-nowrap">{{ $pageLayout->sort_order }}</td>
                                <td class="wb-nowrap">
                                    <div class="wb-action-group">
                                        <a href="{{ route('admin.page-layouts.edit', $pageLayout) }}" class="wb-action-btn wb-action-btn-edit" title="Edit Page Layout" aria-label="Edit Page Layout">
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

        <div class="wb-card-footer wb-text-sm wb-text-muted">
            System layouts cannot be deleted. V1 supports create, edit, activate, deactivate, and ordering.
        </div>

        @include('admin.partials.pagination', ['paginator' => $pageLayouts])
    </div>
@endsection
