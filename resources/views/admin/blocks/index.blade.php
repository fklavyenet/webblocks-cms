@extends('layouts.admin', ['title' => 'Blocks', 'heading' => 'Blocks'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => $currentPage ? 'Blocks for '.$currentPage->title : 'Blocks',
        'description' => $currentPage ? 'Inspect block instances for the selected page.' : 'Inspect and edit block instances across the CMS.',
        'count' => $blocks->total(),
        'actions' => $currentPage && ! $currentPage->isSharedSlotSourcePage() ? '<a href="'.route('admin.pages.edit', $currentPage).'" class="wb-btn wb-btn-primary">Manage Slots</a>' : null,
    ])

    @include('admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            @include('admin.partials.listing-filters', [
                'action' => route('admin.blocks.index'),
                'search' => [
                    'id' => 'blocks_search',
                    'name' => 'search',
                    'label' => 'Search',
                    'value' => $filters['search'],
                    'placeholder' => 'Search blocks, pages, or translated content',
                ],
                'selects' => [
                    [
                        'id' => 'blocks_site',
                        'name' => 'site',
                        'label' => 'Site',
                        'selected' => $filters['site'],
                        'placeholder' => 'All sites',
                        'options' => $filterSites,
                    ],
                    [
                        'id' => 'blocks_page',
                        'name' => 'page_id',
                        'label' => 'Page',
                        'selected' => $filters['page_id'],
                        'placeholder' => 'All pages',
                        'options' => $filterPages,
                    ],
                    [
                        'id' => 'blocks_block_type',
                        'name' => 'block_type_id',
                        'label' => 'Block Type',
                        'selected' => $filters['block_type_id'],
                        'placeholder' => 'All block types',
                        'options' => $filterBlockTypes,
                    ],
                    [
                        'id' => 'blocks_status',
                        'name' => 'status',
                        'label' => 'Status',
                        'selected' => $filters['status'],
                        'placeholder' => 'All statuses',
                        'options' => [
                            'draft' => 'Draft',
                            'published' => 'Published',
                        ],
                    ],
                    [
                        'id' => 'blocks_locale',
                        'name' => 'locale',
                        'label' => 'Locale',
                        'selected' => $filters['locale'],
                        'placeholder' => 'All locales',
                        'options' => $filterLocales,
                    ],
                ],
                'showReset' => $hasActiveFilters,
                'resetUrl' => route('admin.blocks.index'),
                'applyLabel' => 'Apply',
            ])
        </div>
    </div>

    @if ($blocks->isEmpty())
        <div class="wb-card"><div class="wb-card-body"><div class="wb-empty"><div class="wb-empty-title">No blocks found</div><div class="wb-empty-text">Adjust the filters or open a page or shared slot editor to manage block content.</div></div></div></div>
    @else
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead><tr><th>ID</th><th>Page</th><th>Parent</th><th>Block Type</th><th>Slot Type</th><th>Order</th><th>Status</th><th>Kind</th><th>Actions</th></tr></thead>
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
                                        {{ $block->is_system ? 'system' : 'user' }}
                                        @if ($block->isColumnContainer() && $block->children->isNotEmpty())
                                            <div class="wb-text-sm wb-text-muted">children: {{ $block->children->count() }}</div>
                                        @endif
                                    </td>
                                     <td>
                                         <div class="wb-action-group">
                                             <a href="{{ route('admin.blocks.edit', $block) }}" class="wb-action-btn wb-action-btn-edit" title="Edit block" aria-label="Edit block"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                                              <form method="POST" action="{{ route('admin.blocks.destroy', $block) }}" onsubmit="return confirm('Delete this block?');">@csrf @method('DELETE')<button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete block" aria-label="Delete block"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button></form>
                                          </div>
                                      </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @include('admin.partials.pagination', ['paginator' => $blocks, 'ariaLabel' => 'Blocks pagination', 'compact' => true])
        </div>
    @endif
@endsection
