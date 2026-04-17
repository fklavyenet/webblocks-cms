@extends('layouts.admin', ['title' => 'Page Types', 'heading' => 'Page Types'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Page Types',
        'description' => 'Manage page types and review how many pages use each one.',
        'count' => $pageTypes->total(),
        'actions' => '<a href="'.route('admin.page-types.create').'" class="wb-btn wb-btn-primary">New Page Type</a>',
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
                            <th>Description</th>
                            <th>Pages</th>
                            <th>Sort Order</th>
                            <th>Status</th>
                            <th>System</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pageTypes as $pageType)
                            <tr>
                                <td class="wb-nowrap"><strong>{{ $pageType->name }}</strong></td>
                                <td class="wb-nowrap"><code>{{ $pageType->slug }}</code></td>
                                <td class="wb-text-muted">{{ $pageType->description ?: '-' }}</td>
                                <td class="wb-nowrap">{{ $pageType->pages_count }}</td>
                                <td class="wb-nowrap">{{ $pageType->sort_order }}</td>
                                <td><span class="wb-status-pill {{ $pageType->status === 'published' ? 'wb-status-active' : 'wb-status-pending' }}">{{ $pageType->status }}</span></td>
                                <td><span class="wb-status-pill {{ $pageType->is_system ? 'wb-status-info' : 'wb-status-pending' }}">{{ $pageType->is_system ? 'system' : 'user' }}</span></td>
                                <td class="wb-nowrap">
                                    <div class="wb-action-group">
                                        <a href="{{ route('admin.page-types.edit', $pageType) }}" class="wb-action-btn wb-action-btn-edit" title="Edit page type" aria-label="Edit page type">
                                            <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.page-types.destroy', $pageType) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete page type" aria-label="Delete page type">
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

        @include('admin.partials.pagination', ['paginator' => $pageTypes])
    </div>
@endsection
