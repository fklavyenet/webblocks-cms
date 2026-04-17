@php
    $columnItems = old('column_items')
        ? collect(old('column_items'))->map(function (array $submittedItem, int $index) use ($block, $columnItemBlockType) {
            $columnItem = new \App\Models\Block($submittedItem);
            $columnItem->page_id = $block->page_id;
            $columnItem->parent_id = $block->id;
            $columnItem->slot_type_id = $block->slot_type_id;
            $columnItem->sort_order = $submittedItem['sort_order'] ?? $index;
            $columnItem->block_type_id = $submittedItem['block_type_id'] ?? $columnItemBlockType?->id;
            $columnItem->type = $columnItemBlockType?->slug ?? 'column_item';

            return $columnItem;
        })
        : $block->children
            ->filter(fn ($child) => $child->isColumnItem())
            ->sortBy('sort_order')
            ->values();
@endphp

@if ($columnItemBlockType)
    <div class="wb-card wb-card-muted" data-wb-column-items-editor>
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <div class="wb-stack wb-gap-1">
                <strong>Column Items</strong>
                <span class="wb-text-sm wb-text-muted">Each visible column is a child block under this Columns container.</span>
            </div>

            <button type="button" class="wb-btn wb-btn-secondary" data-wb-column-item-add>Add Column</button>
        </div>

        <div class="wb-card-body">
            <div class="wb-stack wb-gap-3" data-wb-column-item-list>
                @forelse ($columnItems as $index => $columnItem)
                    @include('admin.blocks.partials.column-items-editor-row', [
                        'columnItem' => $columnItem,
                        'index' => $index,
                        'columnItemBlockType' => $columnItemBlockType,
                    ])
                @empty
                    <div class="wb-empty" data-wb-column-item-empty>
                        <div class="wb-empty-title">No column items yet</div>
                        <div class="wb-empty-text">Add the first item to build the visible columns for this section.</div>
                    </div>
                @endforelse
            </div>
        </div>

        <template data-wb-column-item-template>
            @include('admin.blocks.partials.column-items-editor-row', [
                'columnItem' => new \App\Models\Block([
                    'title' => null,
                    'content' => null,
                    'url' => null,
                    'status' => 'published',
                    'is_system' => false,
                ]),
                'index' => '__INDEX__',
                'columnItemBlockType' => $columnItemBlockType,
            ])
        </template>

        <input type="hidden" data-wb-column-item-next-index value="{{ $columnItems->count() }}">
    </div>
@endif
