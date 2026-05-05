@php
    $depth = $depth ?? 0;
    $hasChildren = $block->children->isNotEmpty();
    $canAddChildren = $block->canAcceptChildren();
    $isExpanded = $expandedBlockIds->contains($block->id);
    $rowId = 'slot-block-row-'.$block->id;
    $controlledRowIds = $block->children->pluck('id')->map(fn ($id) => 'slot-block-row-'.$id)->implode(' ');
    $blockAdminSummary = app(\App\Support\Blocks\BlockAdminSummary::class);
    $rowSummary = $blockAdminSummary->primary($block);
    $childCount = $block->children->count();
    $sharedSlot = $sharedSlot ?? null;
@endphp

<tbody
    data-admin-sortable-item
    data-block-id="{{ $block->id }}"
    data-parent-id="{{ $block->parent_id ?? '' }}"
    data-slot-type-id="{{ $block->slot_type_id }}"
    data-depth="{{ $depth }}"
    draggable="true"
>
    <tr
        id="{{ $rowId }}"
        class="wb-block-row wb-block-row-depth-{{ min($depth, 6) }}"
        data-slot-block-row
        data-block-id="{{ $block->id }}"
        data-wb-slot-block-row
        data-wb-slot-block-id="{{ $block->id }}"
        data-depth="{{ $depth }}"
        @if ($parentBlock)
            data-slot-parent-id="{{ $parentBlock->id }}"
            data-wb-slot-parent-id="{{ $parentBlock->id }}"
        @endif
        data-slot-depth="{{ $depth }}"
        data-wb-slot-depth="{{ $depth }}"
    >
        <td class="wb-block-hierarchy-cell wb-admin-slot-block-type-cell">
            <div class="wb-block-hierarchy">
                <div class="wb-cms-block-tree-item">
                    <button type="button" class="wb-action-btn" data-admin-sortable-handle aria-label="Drag to reorder block" title="Drag to reorder block">
                        <i class="wb-icon wb-icon-grip-vertical" aria-hidden="true"></i>
                    </button>
                    @if ($hasChildren)
                        <button
                            type="button"
                            class="wb-action-btn wb-cms-block-tree-toggle"
                            data-slot-block-toggle
                            data-slot-toggle="{{ $block->id }}"
                            data-wb-slot-block-toggle
                            data-wb-slot-toggle="{{ $block->id }}"
                            @if ($controlledRowIds !== '') aria-controls="{{ $controlledRowIds }}" @endif
                            aria-expanded="{{ $isExpanded ? 'true' : 'false' }}"
                            aria-label="{{ $isExpanded ? 'Collapse child blocks' : 'Expand child blocks' }}"
                            title="{{ $isExpanded ? 'Collapse child blocks' : 'Expand child blocks' }}"
                        >
                            <i class="wb-icon wb-icon-chevron-down wb-cms-block-tree-toggle-icon" aria-hidden="true"></i>
                        </button>
                    @endif

                    <span class="wb-cms-block-tree-label wb-admin-slot-block-type"><strong>{{ $block->typeName() }}</strong></span>
                </div>
            </div>
        </td>
        <td class="wb-admin-slot-block-summary-cell">
            <a href="{{ $slotBlockRoute(['edit' => $block->id]) }}" data-wb-slot-block-link data-base-url="{{ $slotBlockBaseRoute(['edit' => $block->id]) }}"><strong class="wb-cms-block-row-title">{{ $rowSummary }}</strong></a>
        </td>
        <td class="wb-cms-block-children-cell">
            @if ($canAddChildren || $hasChildren)
                <span class="wb-cms-block-children-badge" aria-label="{{ $childCount }} {{ \Illuminate\Support\Str::plural('child block', $childCount) }}">{{ $childCount }}</span>
            @else
                <span class="wb-text-muted" aria-hidden="true">-</span>
            @endif
        </td>
        <td class="wb-admin-slot-block-status-cell">
            <span class="wb-status-pill {{ $block->status === 'published' ? 'wb-status-active' : 'wb-status-pending' }}">
                {{ $block->status }}
            </span>
        </td>
        <td class="wb-admin-slot-block-actions-cell">
            <div class="wb-action-group">
                <form method="POST" action="{{ route('admin.blocks.move-up', $block) }}">
                    @csrf
                    @if ($sharedSlot)
                        <input type="hidden" name="shared_slot_id" value="{{ $sharedSlot->id }}">
                    @endif
                    @unless ($activeLocale->is_default)
                        <input type="hidden" name="locale" value="{{ $activeLocale->code }}">
                    @endunless
                    <button type="submit" class="wb-action-btn" title="Move block up" aria-label="Move block up"><i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i></button>
                </form>
                <form method="POST" action="{{ route('admin.blocks.move-down', $block) }}">
                    @csrf
                    @if ($sharedSlot)
                        <input type="hidden" name="shared_slot_id" value="{{ $sharedSlot->id }}">
                    @endif
                    @unless ($activeLocale->is_default)
                        <input type="hidden" name="locale" value="{{ $activeLocale->code }}">
                    @endunless
                    <button type="submit" class="wb-action-btn" title="Move block down" aria-label="Move block down"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
                </form>
                <a href="{{ $slotBlockRoute(['edit' => $block->id]) }}" class="wb-action-btn wb-action-btn-edit" title="Edit block" aria-label="Edit block" data-wb-slot-block-link data-base-url="{{ $slotBlockBaseRoute(['edit' => $block->id]) }}"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                @if ($canAddChildren)
                    <a href="{{ $slotBlockRoute(['picker' => 1, 'parent_id' => $block->id]) }}" class="wb-action-btn" title="Add child block" aria-label="Add child block" data-wb-slot-block-link data-base-url="{{ $slotBlockBaseRoute(['picker' => 1, 'parent_id' => $block->id]) }}"><i class="wb-icon wb-icon-plus" aria-hidden="true"></i></a>
                @endif
                <form method="POST" action="{{ route('admin.blocks.destroy', $block) }}" onsubmit="return confirm('Delete this block?');">
                    @csrf
                    @method('DELETE')
                    @if ($sharedSlot)
                        <input type="hidden" name="shared_slot_id" value="{{ $sharedSlot->id }}">
                    @endif
                    @unless ($activeLocale->is_default)
                        <input type="hidden" name="locale" value="{{ $activeLocale->code }}">
                    @endunless
                    <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete block" aria-label="Delete block"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                </form>
            </div>
        </td>
    </tr>
</tbody>

@foreach ($block->children as $child)
    @include('admin.pages.partials.slot-block-row', [
        'block' => $child,
        'parentBlock' => $block,
        'depth' => $depth + 1,
        'page' => $page,
        'slot' => $slot,
        'slotBlockRoute' => $slotBlockRoute,
        'slotBlockBaseRoute' => $slotBlockBaseRoute,
        'activeLocale' => $activeLocale,
        'expandedBlockIds' => $expandedBlockIds,
        'sharedSlot' => $sharedSlot,
    ])
@endforeach
