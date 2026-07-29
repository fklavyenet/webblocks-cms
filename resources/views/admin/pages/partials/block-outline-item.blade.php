@php
    $slotRouteId = \WebBlocks\Cms\Models\PageSlot::query()
        ->where('page_id', $page->id)
        ->where('slot_type_id', $item['block']->slot_type_id)
        ->value('id');
    $inlineBlocksText = fn (string $key, array $replace = []) => __('webblocks-cms::admin.inline_blocks.'.$key, $replace);
@endphp

<div class="wb-card wb-card-muted">
    <div class="wb-card-body">
        <div class="wb-stack wb-stack-2">
            <div class="wb-cluster wb-cluster-between wb-cluster-2">
                <div class="wb-stack wb-stack-1">
                    <div class="wb-cluster wb-cluster-2">
                        <strong>{{ str_repeat('— ', $item['depth']) }}{{ $item['block']->title ?: ($item['block']->blockType?->name ?? ucfirst($item['block']->type)) }}</strong>
                        <span class="wb-status-pill wb-status-info">{{ $item['block']->typeName() }}</span>
                        <span class="wb-status-pill {{ $item['block']->status === 'published' ? 'wb-status-active' : 'wb-status-pending' }}">{{ $item['block']->status }}</span>
                    </div>

                    <div class="wb-cluster wb-cluster-2 wb-text-sm wb-text-muted">
                        <span>{{ $item['block']->slotName() }}</span>
                        <span>#{{ $item['block']->id }}</span>
                    </div>
                </div>

                <div class="wb-action-group">
                    <form method="POST" action="{{ route('admin.blocks.move-up', $item['block']) }}">
                        @csrf
                        <button type="submit" class="wb-action-btn" title="{{ $inlineBlocksText('move_up') }}" aria-label="{{ $inlineBlocksText('move_up') }}">
                            <i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.blocks.move-down', $item['block']) }}">
                        @csrf
                        <button type="submit" class="wb-action-btn" title="{{ $inlineBlocksText('move_down') }}" aria-label="{{ $inlineBlocksText('move_down') }}">
                            <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>
                        </button>
                    </form>

                    <a href="{{ $slotRouteId ? route('admin.pages.slots.blocks', ['page' => $page, 'slot' => $slotRouteId, 'edit' => $item['block']->id]) : route('admin.blocks.edit', $item['block']) }}" class="wb-action-btn wb-action-btn-edit" title="{{ $inlineBlocksText('edit') }}" aria-label="{{ $inlineBlocksText('edit') }}">
                        <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                    </a>

                    <a href="{{ $slotRouteId ? route('admin.pages.slots.blocks', ['page' => $page, 'slot' => $slotRouteId, 'picker' => 1]) : route('admin.pages.edit', $page) }}" class="wb-action-btn" title="{{ $inlineBlocksText('add_in_slot') }}" aria-label="{{ $inlineBlocksText('add_in_slot') }}">
                        <i class="wb-icon wb-icon-plus" aria-hidden="true"></i>
                    </a>

                    <button
                        type="button"
                        class="wb-action-btn wb-action-btn-delete"
                        data-wb-toggle="modal"
                        data-wb-target="#delete-outline-block-{{ $item['block']->id }}"
                        title="{{ $inlineBlocksText('delete') }}"
                        aria-label="{{ $inlineBlocksText('delete') }}"
                        aria-haspopup="dialog"
                    >
                        <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            {{-- The outline recurses, so each level registers its own confirmation. --}}
            @push('overlays')
                @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                    'id' => 'delete-outline-block-'.$item['block']->id,
                    'title' => $inlineBlocksText('delete_title'),
                    'description' => $inlineBlocksText('delete_description'),
                    'action' => route('admin.blocks.destroy', $item['block']),
                    'method' => 'DELETE',
                    'submitLabel' => $inlineBlocksText('delete'),
                ])
                    <p>{{ $inlineBlocksText('delete_confirm_prefix') }} <strong>{{ $item['block']->title ?: ($item['block']->blockType?->name ?? ucfirst($item['block']->type)) }}</strong> (#{{ $item['block']->id }})? {{ $inlineBlocksText('cannot_be_undone') }}</p>

                    @if ($item['children']->isNotEmpty())
                        <div class="wb-alert wb-alert-warning">
                            {{ $inlineBlocksText('delete_children_warning', ['count' => $item['children']->count()]) }}
                        </div>
                    @endif
                @endcomponent
            @endpush

            @if ($item['children']->isNotEmpty())
                <div class="wb-stack wb-stack-2">
                    @foreach ($item['children'] as $child)
                        @include('webblocks-cms::admin.pages.partials.block-outline-item', ['item' => $child, 'page' => $page])
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
