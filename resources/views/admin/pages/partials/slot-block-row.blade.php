@php
    $depth = $depth ?? 0;
    $parentBlock = $parentBlock ?? null;
    $hasChildren = $block->children->isNotEmpty();
    $isExpanded = $expandedBlockIds->contains($block->id);
    $rowId = 'slot-block-row-'.$block->id;
    $controlledRowIds = $block->children->pluck('id')->map(fn ($id) => 'slot-block-row-'.$id)->implode(' ');
    $rowExpandedQuery = collect([$block->id])->merge($expandedBlockIds)->unique()->implode(',');
@endphp

<tbody>
    <tr
        id="{{ $rowId }}"
        data-wb-slot-block-row
        data-wb-slot-block-id="{{ $block->id }}"
        @if ($parentBlock)
            data-wb-slot-parent-id="{{ $parentBlock->id }}"
        @endif
        data-wb-slot-depth="{{ $depth }}"
    >
        <td>{{ $depth === 0 ? $block->sort_order : (($parentBlock?->sort_order ?? 0).'.'.($block->sort_order + 1)) }}</td>
        <td>
            <div class="wb-stack wb-gap-1">
                <div class="wb-cms-block-tree-item" data-wb-cms-block-level="{{ $depth }}" style="--wb-cms-block-level: {{ $depth }};">
                    @if ($hasChildren)
                        <button
                            type="button"
                            class="wb-action-btn wb-cms-block-tree-toggle"
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

                    <span class="wb-cms-block-tree-label"><strong>{{ $block->typeName() }}</strong></span>
                </div>

                <span class="wb-text-sm wb-text-muted">
                    {{ $block->is_system ? 'System block' : 'Visitor-facing block' }}
                    @if ($parentBlock)
                        | Child of {{ $parentBlock->editorLabel() }}
                    @endif
                    @if ($hasChildren)
                        | Children: {{ $block->children->count() }} {{ \Illuminate\Support\Str::plural('item', $block->children->count()) }}
                    @endif
                </span>
            </div>
        </td>
        <td>
            <div class="wb-stack wb-gap-1">
                <a href="{{ $slotBlockRoute(['edit' => $block->id, 'expanded' => $expandedBlockQuery !== '' ? $expandedBlockQuery : null]) }}" data-wb-slot-block-link data-base-url="{{ $slotBlockBaseRoute(['edit' => $block->id]) }}"><strong>{{ $block->editorLabel() }}</strong></a>
                @if ($block->editorSummary())
                    <span class="wb-text-sm wb-text-muted">{{ $block->editorSummary() }}</span>
                @endif
                @php($translationStatus = $block->translationStatus($activeLocale))
                <span class="wb-text-sm wb-text-muted">{{ $translationStatus['label'] }}{{ $translationStatus['state'] === 'fallback' ? ' from '.strtoupper($translationStatus['resolved_locale']->code) : '' }}</span>
            </div>
        </td>
        <td>
            <span class="wb-status-pill {{ $block->status === 'published' ? 'wb-status-active' : 'wb-status-pending' }}">
                {{ $block->status }}
            </span>
        </td>
        <td>
            <div class="wb-action-group">
                <form method="POST" action="{{ route('admin.blocks.move-up', $block) }}">
                    @csrf
                    <input type="hidden" name="expanded" value="{{ $expandedBlockQuery }}" data-wb-slot-block-expanded-input>
                    @unless ($activeLocale->is_default)
                        <input type="hidden" name="locale" value="{{ $activeLocale->code }}">
                    @endunless
                    <button type="submit" class="wb-action-btn" title="Move block up" aria-label="Move block up"><i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i></button>
                </form>
                <form method="POST" action="{{ route('admin.blocks.move-down', $block) }}">
                    @csrf
                    <input type="hidden" name="expanded" value="{{ $expandedBlockQuery }}" data-wb-slot-block-expanded-input>
                    @unless ($activeLocale->is_default)
                        <input type="hidden" name="locale" value="{{ $activeLocale->code }}">
                    @endunless
                    <button type="submit" class="wb-action-btn" title="Move block down" aria-label="Move block down"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
                </form>
                <a href="{{ $slotBlockRoute(['edit' => $block->id, 'expanded' => $expandedBlockQuery !== '' ? $expandedBlockQuery : null]) }}" class="wb-action-btn wb-action-btn-edit" title="Edit block" aria-label="Edit block" data-wb-slot-block-link data-base-url="{{ $slotBlockBaseRoute(['edit' => $block->id]) }}"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                <form method="POST" action="{{ route('admin.blocks.destroy', $block) }}" onsubmit="return confirm('Delete this block?');">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="expanded" value="{{ $expandedBlockQuery }}" data-wb-slot-block-expanded-input>
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
        'expandedBlockQuery' => $rowExpandedQuery,
    ])
@endforeach
