@extends('layouts.admin', ['title' => 'Layout Types', 'heading' => 'Layout Types'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Layout Types',
        'description' => 'Manage layout types and review how many layouts use each one.',
        'count' => $layoutTypes->total(),
        'actions' => '<a href="'.route('admin.layout-types.create').'" class="wb-btn wb-btn-primary">New Layout Type</a>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Layouts</th>
                            <th>Sort Order</th>
                            <th>Status</th>
                            <th>System</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($layoutTypes as $layoutType)
                            <tr>
                                <td class="wb-nowrap"><strong>{{ $layoutType->name }}</strong></td>
                                <td class="wb-nowrap"><code>{{ $layoutType->slug }}</code></td>
                                <td class="wb-nowrap">{{ $layoutType->category ?: '-' }}</td>
                                <td class="wb-text-muted">{{ $layoutType->description ?: '-' }}</td>
                                <td class="wb-nowrap">{{ $layoutType->layouts_count }}</td>
                                <td class="wb-nowrap">{{ $layoutType->sort_order }}</td>
                                <td><span class="wb-status-pill {{ $layoutType->status === 'published' ? 'wb-status-active' : 'wb-status-pending' }}">{{ $layoutType->status }}</span></td>
                                <td><span class="wb-status-pill {{ $layoutType->is_system ? 'wb-status-info' : 'wb-status-pending' }}">{{ $layoutType->is_system ? 'system' : 'user' }}</span></td>
                                <td class="wb-nowrap">
                                    <div class="wb-action-group">
                                        <a href="{{ route('admin.layout-types.edit', $layoutType) }}" class="wb-action-btn wb-action-btn-edit" title="Edit layout type" aria-label="Edit layout type">
                                            <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.layout-types.destroy', $layoutType) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete layout type" aria-label="Delete layout type">
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

        @include('admin.partials.pagination', ['paginator' => $layoutTypes])
    </div>
@endsection
