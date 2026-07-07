@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $blockTypesIndexLocale = app(AdminLocaleResolver::class)->locale();
    $blockTypesIndexTranslator = app(CmsTranslator::class);
    $blockTypesIndexText = static fn (string $key, array $replace = []) => $blockTypesIndexTranslator->admin('block_types_index.'.$key, $blockTypesIndexLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $blockTypesIndexText('block_types'), 'heading' => $blockTypesIndexText('block_types')])

@section('content')
    @php
        $hasActiveFilters = $filters['search'] !== '' || $filters['category'] !== '' || $filters['status'] !== '' || $filters['support'] !== '' || $filters['usage'] !== '';
        $baseQuery = array_filter(array_merge($filters, ['page' => $blockTypes->currentPage() > 1 ? $blockTypes->currentPage() : null]));
    @endphp

    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $blockTypesIndexText('block_types'),
        'description' => $blockTypesIndexText('description'),
        'count' => $totalCount,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            @include('webblocks-cms::admin.partials.listing-filters', [
                'action' => route('admin.block-types.index'),
                'search' => [
                    'id' => 'block_types_search',
                    'name' => 'search',
                    'label' => $blockTypesIndexText('search'),
                    'value' => $filters['search'],
                    'placeholder' => $blockTypesIndexText('search_placeholder'),
                ],
                'selects' => [
                    [
                        'id' => 'block_types_category',
                        'name' => 'category',
                        'label' => $blockTypesIndexText('category'),
                        'value' => $filters['category'],
                        'placeholder' => $blockTypesIndexText('all_categories'),
                        'options' => collect($categories)->mapWithKeys(fn (string $category) => [$category => ucfirst($category)])->all(),
                    ],
                    [
                        'id' => 'block_types_status',
                        'name' => 'status',
                        'label' => $blockTypesIndexText('status'),
                        'value' => $filters['status'],
                        'placeholder' => $blockTypesIndexText('all_statuses'),
                        'options' => collect($statuses)->mapWithKeys(fn (string $status) => [$status => ucfirst(str_replace('_', ' ', $status))])->all(),
                    ],
                    [
                        'id' => 'block_types_usage',
                        'name' => 'usage',
                        'label' => $blockTypesIndexText('usage'),
                        'value' => $filters['usage'],
                        'placeholder' => $blockTypesIndexText('all_usage'),
                        'options' => [
                            'used' => $blockTypesIndexText('used'),
                            'unused' => $blockTypesIndexText('unused'),
                        ],
                    ],
                    [
                        'id' => 'block_types_support',
                        'name' => 'support',
                        'label' => $blockTypesIndexText('support'),
                        'value' => $filters['support'],
                        'placeholder' => $blockTypesIndexText('all_support'),
                        'options' => $supportOptions,
                    ],
                ],
                'showReset' => $hasActiveFilters,
                'resetUrl' => route('admin.block-types.index'),
                'applyLabel' => $blockTypesIndexText('apply_filters'),
            ])
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <strong>{{ $blockTypesIndexText('block_types') }}</strong>
                <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
            </div>

            <a href="{{ route('admin.block-types.create') }}" class="wb-btn wb-btn-primary">{{ $blockTypesIndexText('new_custom_block_type') }}</a>
        </div>

        @if ($blockTypes->isEmpty())
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $blockTypesIndexText('empty_title') }}</div>
                    <div class="wb-empty-text">{{ $blockTypesIndexText('empty_text') }}</div>
                    @if ($hasActiveFilters)
                        <div class="wb-empty-action">
                            <a href="{{ route('admin.block-types.index') }}" class="wb-btn wb-btn-secondary">{{ $blockTypesIndexText('reset') }}</a>
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
                                <th>{{ $blockTypesIndexText('name') }}</th>
                                <th>{{ $blockTypesIndexText('category') }}</th>
                                <th>{{ $blockTypesIndexText('usage') }}</th>
                                <th>{{ $blockTypesIndexText('status') }}</th>
                                <th>{{ $blockTypesIndexText('support') }}</th>
                                <th>{{ $blockTypesIndexText('actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($blockTypes as $blockType)
                                <tr>
                                        <td>
                                            <div class="wb-stack wb-stack-1">
                                                <strong>{{ $blockType->name }}</strong>
                                                <span class="wb-text-sm wb-text-muted"><code>{{ $blockType->slug }}</code> | {{ $blockType->source_type ?: 'static' }} | {{ $blockType->is_system ? $blockTypesIndexText('system') : $blockTypesIndexText('user') }}{{ $blockType->is_container ? ' | '.$blockTypesIndexText('container') : '' }}</span>
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
                                                <span class="wb-text-sm wb-text-muted">{{ $blockTypesIndexText('admin') }} {!! ($supportedAdminForms[$blockType->id] ?? false) ? '&#10003;' : '&#8722;' !!}</span>
                                                <span class="wb-text-sm wb-text-muted">{{ $blockTypesIndexText('render') }} {!! ($supportedPublicRenders[$blockType->id] ?? false) ? '&#10003;' : '&#8722;' !!}</span>
                                            </div>
                                        </td>
                                        <td class="wb-nowrap">
                                            <div class="wb-action-group">
                                                <a
                                                    href="{{ route('admin.block-types.index', array_filter(array_merge($baseQuery, ['modal' => 'block-type-contract', 'contract_block_type' => $blockType->id]))) }}"
                                                    class="wb-action-btn wb-action-btn-view"
                                                    title="{{ $blockTypesIndexText('view_contract') }}"
                                                    aria-label="{{ $blockTypesIndexText('view_contract') }}"
                                                    aria-haspopup="dialog"
                                                    aria-controls="blockTypeContractModal-{{ $blockType->id }}"
                                                    data-admin-block-type-contract-action
                                                >
                                                    <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                                </a>

                                                @if (! $blockType->is_system)
                                                    <a href="{{ route('admin.block-types.index', array_filter(array_merge($baseQuery, ['modal' => 'edit-block-type', 'block_type' => $blockType->id]))) }}" class="wb-action-btn wb-action-btn-edit" title="{{ $blockTypesIndexText('edit_block_type') }}" aria-label="{{ $blockTypesIndexText('edit_block_type') }}" aria-haspopup="dialog" aria-controls="blockTypeEditModal-{{ $blockType->id }}">
                                                        <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                                    </a>
                                                    <form method="POST" action="{{ route('admin.block-types.destroy', $blockType) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="wb-action-btn wb-action-btn-delete" title="{{ $blockTypesIndexText('delete_block_type') }}" aria-label="{{ $blockTypesIndexText('delete_block_type') }}">
                                                            <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="wb-text-sm wb-text-muted">{{ $blockTypesIndexText('core_catalog') }}</span>
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

        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $blockTypes, 'ariaLabel' => $blockTypesIndexText('pagination'), 'compact' => true])
    </div>
@endsection

@push('overlays')
    @if ($requestedModal === 'block-type-contract' && $contractBlockType)
        @include('webblocks-cms::admin.block-types.partials.contract-modal', [
            'blockType' => $contractBlockType,
            'contract' => $blockTypeContracts[$contractBlockType->id] ?? null,
            'closeUrl' => $closeUrl,
        ])
    @endif

    @if ($requestedModal === 'edit-block-type' && $editBlockType)
        @include('webblocks-cms::admin.block-types.partials.edit-modal', [
            'blockType' => $editBlockType,
            'closeUrl' => $closeUrl,
            'blockTypesReturnUrl' => $blockTypesReturnUrl,
        ])
    @endif
@endpush
