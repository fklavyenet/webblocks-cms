@extends('layouts.admin', ['title' => 'Block Types', 'heading' => 'Block Types'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Block Types',
        'description' => 'Review the CMS block catalog. System block types are product-owned; non-system entries are install-specific extensions.',
        'count' => $blockTypes->total(),
        'actions' => '<a href="'.route('admin.block-types.create').'" class="wb-btn wb-btn-primary">New Custom Block Type</a>',
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
                            <th>Status</th>
                            <th>System</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($blockTypes as $blockType)
                            <tr>
                                    <td><strong>{{ $blockType->name }}</strong></td>
                                    <td class="wb-nowrap"><code>{{ $blockType->slug }}</code></td>
                                    <td class="wb-nowrap">{{ $blockType->category ?: '-' }}</td>
                                    <td class="wb-text-muted">{{ $blockType->description ?: '-' }}</td>
                                    <td>
                                        <span class="wb-status-pill {{ $blockType->status === 'published' ? 'wb-status-active' : 'wb-status-pending' }}">
                                            {{ $blockType->status }}
                                        </span>
                                    </td>
                                    <td><span class="wb-status-pill {{ $blockType->is_system ? 'wb-status-info' : 'wb-status-pending' }}">{{ $blockType->is_system ? 'system' : 'user' }}</span></td>
                                    <td class="wb-nowrap">
                                        <div class="wb-action-group">
                                            @if (! $blockType->is_system)
                                                <a href="{{ route('admin.block-types.edit', $blockType) }}" class="wb-action-btn wb-action-btn-edit" title="Edit block type" aria-label="Edit block type">
                                                    <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                                </a>
                                                <form method="POST" action="{{ route('admin.block-types.destroy', $blockType) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete block type" aria-label="Delete block type">
                                                        <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <span class="wb-text-sm wb-text-muted">Core catalog</span>
                                            @endif
                                        </div>
                                    </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @include('admin.partials.pagination', ['paginator' => $blockTypes])
    </div>
@endsection
