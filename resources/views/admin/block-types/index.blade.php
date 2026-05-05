@extends('layouts.admin', ['title' => 'Block Types', 'heading' => 'Block Types'])

@section('content')
    @php
        $hasActiveFilters = $filters['search'] !== '' || $filters['category'] !== '' || $filters['status'] !== '' || $filters['support'] !== '';
    @endphp

    @include('admin.partials.page-header', [
        'title' => 'Block Types',
        'description' => 'Review the CMS block catalog. System block types are product-owned; non-system entries are install-specific extensions.',
        'count' => $blockTypes->total(),
        'actions' => '<a href="'.route('admin.block-types.create').'" class="wb-btn wb-btn-primary">New Custom Block Type</a>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            <form method="GET" action="{{ route('admin.block-types.index') }}" class="wb-stack wb-gap-3">
                <div class="wb-grid wb-grid-4 wb-gap-3">
                    <div class="wb-stack wb-gap-1 wb-field">
                        <label for="block_types_search" class="wb-label">Search</label>
                        <input id="block_types_search" name="search" type="text" class="wb-input" value="{{ $filters['search'] }}" placeholder="Search block types...">
                    </div>

                    <div class="wb-stack wb-gap-1 wb-field">
                        <label for="block_types_category" class="wb-label">Category</label>
                        <select id="block_types_category" name="category" class="wb-select">
                            <option value="">All categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ ucfirst($category) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wb-stack wb-gap-1 wb-field">
                        <label for="block_types_status" class="wb-label">Status</label>
                        <select id="block_types_status" name="status" class="wb-select">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wb-stack wb-gap-1 wb-field">
                        <label for="block_types_support" class="wb-label">Support</label>
                        <select id="block_types_support" name="support" class="wb-select">
                            <option value="">All support</option>
                            @foreach ($supportOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['support'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="wb-cluster wb-cluster-2 wb-admin-filter-actions-end">
                    <button type="submit" class="wb-btn wb-btn-primary">Apply filters</button>
                    @if ($hasActiveFilters)
                        <a href="{{ route('admin.block-types.index') }}" class="wb-btn wb-btn-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="wb-card">
        @if ($blockTypes->isEmpty())
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">No block types found.</div>
                    <div class="wb-empty-text">Try changing your filters.</div>
                    @if ($hasActiveFilters)
                        <div class="wb-empty-action">
                            <a href="{{ route('admin.block-types.index') }}" class="wb-btn wb-btn-secondary">Reset</a>
                        </div>
                    @endif
                </div>
            </div>
        @else
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
        @endif

        @include('admin.partials.pagination', ['paginator' => $blockTypes, 'ariaLabel' => 'Block types pagination', 'compact' => true])
    </div>
@endsection
