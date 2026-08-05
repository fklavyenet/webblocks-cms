@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $blocksIndexLocale = app(AdminLocaleResolver::class)->locale();
    $blocksIndexTranslator = app(CmsTranslator::class);
    $blocksIndexText = static fn (string $key, array $replace = []) => $blocksIndexTranslator->admin('blocks_index.'.$key, $blocksIndexLocale, $replace);
    $blocksIndexTitle = $currentPage ? $blocksIndexText('blocks_for_page', ['title' => $currentPage->title]) : $blocksIndexText('blocks');
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $blocksIndexText('blocks'), 'heading' => $blocksIndexText('blocks')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $blocksIndexTitle,
        'description' => $currentPage ? $blocksIndexText('page_description') : $blocksIndexText('description'),
        'count' => $totalCount,
        'actions' => $currentPage && ! $currentPage->isSharedSlotSourcePage() ? '<a href="'.route('admin.pages.edit', $currentPage).'" class="wb-btn wb-btn-primary">'.$blocksIndexText('manage_slots').'</a>' : null,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            @include('webblocks-cms::admin.partials.listing-filters', [
                'action' => route('admin.blocks.index'),
                'search' => [
                    'id' => 'blocks_search',
                    'name' => 'search',
                    'label' => $blocksIndexText('search'),
                    'value' => $filters['search'],
                    'placeholder' => $blocksIndexText('search_placeholder'),
                ],
                'selects' => [
                    [
                        'id' => 'blocks_site',
                        'name' => 'site',
                        'label' => $blocksIndexText('site'),
                        'selected' => $filters['site'],
                        'placeholder' => $blocksIndexText('all_sites'),
                        'options' => $filterSites,
                    ],
                    [
                        'id' => 'blocks_page',
                        'name' => 'page_id',
                        'label' => $blocksIndexText('page'),
                        'selected' => $filters['page_id'],
                        'placeholder' => $blocksIndexText('all_pages'),
                        'options' => $filterPages,
                    ],
                    [
                        'id' => 'blocks_block_type',
                        'name' => 'block_type_id',
                        'label' => $blocksIndexText('block_type'),
                        'selected' => $filters['block_type_id'],
                        'placeholder' => $blocksIndexText('all_block_types'),
                        'options' => $filterBlockTypes,
                    ],
                    [
                        'id' => 'blocks_status',
                        'name' => 'status',
                        'label' => $blocksIndexText('status'),
                        'selected' => $filters['status'],
                        'placeholder' => $blocksIndexText('all_statuses'),
                        'options' => [
                            'draft' => $blocksIndexText('draft'),
                            'published' => $blocksIndexText('published'),
                        ],
                    ],
                    [
                        'id' => 'blocks_locale',
                        'name' => 'locale',
                        'label' => $blocksIndexText('locale'),
                        'selected' => $filters['locale'],
                        'placeholder' => $blocksIndexText('all_locales'),
                        'options' => $filterLocales,
                    ],
                ],
                'showReset' => $hasActiveFilters,
                'resetUrl' => route('admin.blocks.index'),
                'applyLabel' => $blocksIndexText('apply'),
            ])
        </div>
    </div>

    @if ($blocks->isEmpty())
        <div class="wb-card"><div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap"><div class="wb-cluster wb-cluster-2 wb-flex-wrap"><strong>{{ $blocksIndexTitle }}</strong><span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span></div></div><div class="wb-card-body"><div class="wb-empty"><div class="wb-empty-title">{{ $blocksIndexText('empty_title') }}</div><div class="wb-empty-text">{{ $hasActiveFilters ? $blocksIndexText('empty_filtered_text') : $blocksIndexText('empty_text') }}</div>@if ($hasActiveFilters)<div class="wb-empty-action"><a href="{{ route('admin.blocks.index') }}" class="wb-btn wb-btn-secondary">{{ $blocksIndexText('clear_filters') }}</a></div>@endif</div></div></div>
    @else
        <div class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>{{ $blocksIndexTitle }}</strong>
                    <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
                </div>
            </div>
            <div class="wb-card-body">
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead><tr><th>{{ $blocksIndexText('id') }}</th><th>{{ $blocksIndexText('page') }}</th><th>{{ $blocksIndexText('parent') }}</th><th>{{ $blocksIndexText('block_type') }}</th><th>{{ $blocksIndexText('slot_type') }}</th><th>{{ $blocksIndexText('order') }}</th><th>{{ $blocksIndexText('status') }}</th><th>{{ $blocksIndexText('kind') }}</th><th>{{ $blocksIndexText('actions') }}</th></tr></thead>
                        <tbody>
                            @foreach ($blocks as $block)
                                <tr>
                                    <td>{{ $block->id }}</td>
                                    <td>
                                        @if ($block->page)
                                            <a href="{{ route('admin.pages.edit', $block->page) }}">{{ $block->page->title }}</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $block->parent?->title ?? ($block->parent?->typeName() ?? '-') }}</td>
                                    <td>{{ $block->typeName() }}</td>
                                    <td>{{ $block->slotName() }}</td>
                                    <td>{{ $block->sort_order }}</td>
                                    <td>
                                        <span class="wb-status-pill {{ $block->status === 'published' ? 'wb-status-active' : 'wb-status-pending' }}">
                                            {{ $block->status }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $block->is_system ? $blocksIndexText('system') : $blocksIndexText('user') }}
                                        @if ($block->isColumnContainer() && $block->children->isNotEmpty())
                                            <div class="wb-text-sm wb-text-muted">{{ $blocksIndexText('children_count', ['count' => $block->children->count()]) }}</div>
                                        @endif
                                    </td>
                                     <td>
                                         <div class="wb-action-group">
                                             <a href="{{ route('admin.blocks.edit', $block) }}" class="wb-action-btn wb-action-btn-edit" title="{{ $blocksIndexText('edit_block') }}" aria-label="{{ $blocksIndexText('edit_block') }}"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                                              <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-toggle="modal" data-wb-target="#delete-block-{{ $block->id }}" title="{{ $blocksIndexText('delete_block') }}" aria-label="{{ $blocksIndexText('delete_block') }}" aria-haspopup="dialog"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                                          </div>
                                      </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @include('webblocks-cms::admin.partials.pagination', ['paginator' => $blocks, 'ariaLabel' => $blocksIndexText('pagination'), 'compact' => true])
        </div>
    @endif
@endsection

@push('overlays')
    @foreach ($blocks as $block)
        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
            'id' => 'delete-block-'.$block->id,
            'title' => $blocksIndexText('delete_title'),
            'description' => $blocksIndexText('delete_description'),
            'action' => route('admin.blocks.destroy', $block),
            'method' => 'DELETE',
            'submitLabel' => $blocksIndexText('delete_block'),
        ])
            <p>{{ $blocksIndexText('delete_confirm_prefix') }} <strong>{{ str($block->type)->replace('-', ' ')->headline() }}</strong> (#{{ $block->id }})? {{ $blocksIndexText('cannot_be_undone') }}</p>

            @if ($block->children->isNotEmpty())
                <div class="wb-alert wb-alert-warning">
                    {{ $blocksIndexText('delete_children_warning', ['count' => $block->children->count()]) }}
                </div>
            @endif
        @endcomponent
    @endforeach
@endpush
