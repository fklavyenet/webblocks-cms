@php
    $rowPrefix = is_numeric($index) ? "column_items[{$index}]" : 'column_items[__INDEX__]';
    $rowSortOrder = is_numeric($index) ? ($columnItem->sort_order ?? $index) : '__INDEX__';
@endphp

<div class="wb-card" data-wb-column-item-row>
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
        <div class="wb-stack wb-gap-1">
            <strong data-wb-column-item-label>{{ $columnItem->title ?: 'New Column Item' }}</strong>
            <span class="wb-text-sm wb-text-muted">{{ $columnItem->content ? str(strip_tags((string) $columnItem->content))->squish()->limit(88) : 'Add a title and short description for this column.' }}</span>
        </div>

        <div class="wb-action-group">
            <button type="button" class="wb-action-btn" data-wb-column-item-move="up" title="Move up" aria-label="Move up"><i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i></button>
            <button type="button" class="wb-action-btn" data-wb-column-item-move="down" title="Move down" aria-label="Move down"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
            <button type="button" class="wb-action-btn" data-wb-column-item-toggle title="Collapse item" aria-label="Collapse item"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
            <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-column-item-remove title="Remove item" aria-label="Remove item"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
        </div>
    </div>

    <div class="wb-card-body wb-stack wb-gap-3" data-wb-column-item-body>
        <input type="hidden" name="{{ $rowPrefix }}[id]" value="{{ is_numeric($index) ? $columnItem->id : '' }}">
        <input type="hidden" name="{{ $rowPrefix }}[block_type_id]" value="{{ $columnItemBlockType?->id }}">
        <input type="hidden" name="{{ $rowPrefix }}[sort_order]" value="{{ $rowSortOrder }}" data-wb-column-item-sort>
        <input type="hidden" name="{{ $rowPrefix }}[_delete]" value="0" data-wb-column-item-delete>

        <div class="wb-grid wb-grid-2">
            <div class="wb-stack wb-gap-1">
                <label>Column Title</label>
                <input class="wb-input" type="text" name="{{ $rowPrefix }}[title]" value="{{ $columnItem->title }}" data-wb-column-item-title>
            </div>

            <div class="wb-stack wb-gap-1">
                <label>Optional Link</label>
                <input class="wb-input" type="text" name="{{ $rowPrefix }}[url]" value="{{ $columnItem->url }}">
            </div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label>Column Text</label>
            <textarea class="wb-textarea" rows="4" name="{{ $rowPrefix }}[content]" data-wb-column-item-content>{{ $columnItem->content }}</textarea>
        </div>

        <div class="wb-grid wb-grid-2">
            <div class="wb-stack wb-gap-1">
                <label>Status</label>
                <select class="wb-select" name="{{ $rowPrefix }}[status]">
                    <option value="draft" @selected(($columnItem->status ?? 'published') === 'draft')>draft</option>
                    <option value="published" @selected(($columnItem->status ?? 'published') === 'published')>published</option>
                </select>
            </div>

            <div class="wb-stack wb-gap-1">
                <label>Kind</label>
                <div class="wb-card wb-card-muted">
                    <div class="wb-card-body">
                        <strong>Content Block</strong>
                    </div>
                </div>
                <input type="hidden" name="{{ $rowPrefix }}[is_system]" value="0">
            </div>
        </div>
    </div>
</div>
