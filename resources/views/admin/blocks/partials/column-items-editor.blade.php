@php
    $inputName = $inputName ?? 'column_items';
    $itemBlockType = $itemBlockType ?? null;
    $editorKey = $editorKey ?? 'column-item';
    $editorTitle = $editorTitle ?? 'Items';
    $editorDescription = $editorDescription ?? 'Manage child items for this block.';
    $addButtonLabel = $addButtonLabel ?? 'Add Item';
    $emptyTitle = $emptyTitle ?? 'No items yet';
    $emptyDescription = $emptyDescription ?? 'Add the first item to continue.';
    $newItemLabel = $newItemLabel ?? 'New Item';
    $titleLabel = $titleLabel ?? 'Title';
    $titlePlaceholder = $titlePlaceholder ?? null;
    $subtitleLabel = $subtitleLabel ?? 'Subtitle';
    $subtitlePlaceholder = $subtitlePlaceholder ?? null;
    $showSubtitle = $showSubtitle ?? false;
    $urlLabel = $urlLabel ?? 'URL';
    $contentLabel = $contentLabel ?? 'Content';
    $contentPlaceholder = $contentPlaceholder ?? 'Add content.';

    $columnItems = old($inputName)
        ? collect(old($inputName))->map(function (array $submittedItem, int $index) use ($block, $itemBlockType) {
            $columnItem = new \App\Models\Block($submittedItem);
            $columnItem->page_id = $block->page_id;
            $columnItem->parent_id = $block->id;
            $columnItem->slot_type_id = $block->slot_type_id;
            $columnItem->sort_order = $submittedItem['sort_order'] ?? $index;
            $columnItem->block_type_id = $submittedItem['block_type_id'] ?? $itemBlockType?->id;
            $columnItem->type = $itemBlockType?->slug ?? 'column_item';

            return $columnItem;
        })
        : $block->children
            ->filter(fn ($child) => $itemBlockType?->slug === 'link-list-item' ? $child->isLinkListItem() : $child->isColumnItem())
            ->sortBy('sort_order')
            ->values();
@endphp

@if ($itemBlockType)
    <div class="wb-card wb-card-muted" data-wb-builder-items-editor="{{ $editorKey }}">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <div class="wb-stack wb-gap-1">
                <strong>{{ $editorTitle }}</strong>
                <span class="wb-text-sm wb-text-muted">{{ $editorDescription }}</span>
            </div>

            <button type="button" class="wb-btn wb-btn-secondary" data-wb-builder-item-add="{{ $editorKey }}">{{ $addButtonLabel }}</button>
        </div>

        <div class="wb-card-body">
            <div class="wb-stack wb-gap-3" data-wb-builder-item-list="{{ $editorKey }}">
                @forelse ($columnItems as $index => $columnItem)
                    @include('admin.blocks.partials.column-items-editor-row', [
                        'columnItem' => $columnItem,
                        'index' => $index,
                        'inputName' => $inputName,
                        'itemBlockType' => $itemBlockType,
                        'editorKey' => $editorKey,
                        'newItemLabel' => $newItemLabel,
                        'titleLabel' => $titleLabel,
                        'titlePlaceholder' => $titlePlaceholder,
                        'subtitleLabel' => $subtitleLabel,
                        'subtitlePlaceholder' => $subtitlePlaceholder,
                        'showSubtitle' => $showSubtitle,
                        'urlLabel' => $urlLabel,
                        'contentLabel' => $contentLabel,
                        'contentPlaceholder' => $contentPlaceholder,
                    ])
                @empty
                    <div class="wb-empty" data-wb-builder-item-empty="{{ $editorKey }}">
                        <div class="wb-empty-title">{{ $emptyTitle }}</div>
                        <div class="wb-empty-text">{{ $emptyDescription }}</div>
                    </div>
                @endforelse
            </div>
        </div>

        <template
            data-wb-builder-item-template="{{ $editorKey }}"
            data-empty-title="{{ $emptyTitle }}"
            data-empty-description="{{ $emptyDescription }}"
        >
            @include('admin.blocks.partials.column-items-editor-row', [
                'columnItem' => new \App\Models\Block([
                    'title' => null,
                    'subtitle' => null,
                    'content' => null,
                    'url' => null,
                    'status' => 'published',
                    'is_system' => false,
                ]),
                'index' => '__INDEX__',
                'inputName' => $inputName,
                'itemBlockType' => $itemBlockType,
                'editorKey' => $editorKey,
                'newItemLabel' => $newItemLabel,
                'titleLabel' => $titleLabel,
                'titlePlaceholder' => $titlePlaceholder,
                'subtitleLabel' => $subtitleLabel,
                'subtitlePlaceholder' => $subtitlePlaceholder,
                'showSubtitle' => $showSubtitle,
                'urlLabel' => $urlLabel,
                'contentLabel' => $contentLabel,
                'contentPlaceholder' => $contentPlaceholder,
            ])
        </template>

        <input type="hidden" data-wb-builder-item-next-index="{{ $editorKey }}" value="{{ $columnItems->count() }}">
    </div>
@endif
