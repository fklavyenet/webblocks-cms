@extends('layouts.admin', ['title' => 'Blocks', 'heading' => 'Blocks'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => $currentPage ? 'Blocks for '.$currentPage->title : 'Blocks',
        'description' => $currentPage ? 'Manage block instances for the selected page.' : 'Manage block instances across the CMS.',
        'count' => $blocks->total(),
        'actions' => $currentPage ? '<a href="'.route('admin.pages.edit', $currentPage).'" class="wb-btn wb-btn-primary">Manage Slots</a>' : null,
    ])

    @include('admin.partials.flash')

    @if ($currentPage)
            <div class="wb-card wb-card-muted">
                <div class="wb-card-body">
                <div class="wb-cluster wb-cluster-between wb-cluster-2">
                    <span>Active page filter: {{ $currentPage->title }}</span>
                    <div class="wb-cluster wb-cluster-2">
                        <a href="{{ route('admin.pages.edit', $currentPage) }}" class="wb-btn wb-btn-secondary">Back to Page</a>
                        <a href="{{ route('admin.blocks.index') }}" class="wb-btn wb-btn-secondary">Clear Filter</a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($blocks->isEmpty())
        <div class="wb-card"><div class="wb-card-body"><div class="wb-empty"><div class="wb-empty-title">No blocks yet</div><div class="wb-empty-text">Create blocks from each page's slot editing screen.</div></div></div></div>
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

            @include('admin.partials.pagination', ['paginator' => $blocks])
        </div>
    @endif
@endsection
