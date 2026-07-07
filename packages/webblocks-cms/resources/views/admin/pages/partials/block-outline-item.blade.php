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

                    <form method="POST" action="{{ route('admin.blocks.destroy', $item['block']) }}" onsubmit="return confirm(@js($inlineBlocksText('delete_confirm')));">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="wb-action-btn wb-action-btn-delete" title="{{ $inlineBlocksText('delete') }}" aria-label="{{ $inlineBlocksText('delete') }}">
                            <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
            </div>

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
