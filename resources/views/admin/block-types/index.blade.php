@extends('layouts.admin', ['title' => 'Block Types', 'heading' => 'Block Types'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Block Types',
        'description' => 'Review the block type catalog and its active admin/render support.',
        'count' => $blockTypes->total(),
        'actions' => '<a href="'.route('admin.block-types.create').'" class="wb-btn wb-btn-primary">New Block Type</a>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Usage</th>
                            <th>Status</th>
                            <th>Support</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($blockTypes as $blockType)
                            <tr>
                                    <td>
                                        <div class="wb-stack wb-stack-1">
                                            <strong>{{ $blockType->name }}</strong>
                                            <span class="wb-text-sm wb-text-muted"><code>{{ $blockType->slug }}</code> | {{ $blockType->source_type ?: 'static' }} | {{ $blockType->is_system ? 'system' : 'user' }}{{ $blockType->is_container ? ' | container' : '' }}</span>
                                        </div>
                                    </td>
                                    <td class="wb-nowrap">{{ $blockType->category ?: '-' }}</td>
                                    <td class="wb-nowrap">{{ $blockType->blocks_count }}</td>
                                    <td>
                                        <span class="wb-status-pill {{ $blockType->status === 'published' ? 'wb-status-active' : 'wb-status-pending' }}">
                                            {{ $blockType->status }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="wb-stack wb-stack-1">
                                            <span class="wb-text-sm wb-text-muted">Admin {!! ($supportedAdminForms[$blockType->id] ?? false) ? '&#10003;' : '&#8722;' !!}</span>
                                            <span class="wb-text-sm wb-text-muted">Render {!! ($supportedPublicRenders[$blockType->id] ?? false) ? '&#10003;' : '&#8722;' !!}</span>
                                        </div>
                                    </td>
                                    <td class="wb-nowrap">
                                        <div class="wb-action-group">
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
