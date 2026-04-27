@php
    $inputName = $inputName ?? 'column_items';
    $itemBlockType = $itemBlockType ?? null;
    $editorKey = $editorKey ?? 'column-item';
    $newItemLabel = $newItemLabel ?? 'New Item';
    $titleLabel = $titleLabel ?? 'Title';
    $titlePlaceholder = $titlePlaceholder ?? null;
    $subtitleLabel = $subtitleLabel ?? 'Subtitle';
    $subtitlePlaceholder = $subtitlePlaceholder ?? null;
    $showSubtitle = $showSubtitle ?? false;
    $urlLabel = $urlLabel ?? 'URL';
    $contentLabel = $contentLabel ?? 'Content';
    $contentPlaceholder = $contentPlaceholder ?? 'Add content.';
    $rowPrefix = is_numeric($index) ? "{$inputName}[{$index}]" : "{$inputName}[__INDEX__]";
    $rowSortOrder = is_numeric($index) ? ($columnItem->sort_order ?? $index) : '__INDEX__';
    $summaryText = $showSubtitle
        ? ($columnItem->content ? str(strip_tags((string) $columnItem->content))->squish()->limit(88) : $contentPlaceholder)
        : ($columnItem->content ? str(strip_tags((string) $columnItem->content))->squish()->limit(88) : $contentPlaceholder);
@endphp

<div class="wb-card" data-wb-builder-item-row="{{ $editorKey }}">
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
        <div class="wb-stack wb-gap-1">
            <strong data-wb-builder-item-label="{{ $editorKey }}">{{ $columnItem->title ?: $newItemLabel }}</strong>
            <span class="wb-text-sm wb-text-muted">{{ $summaryText }}</span>
        </div>

        <div class="wb-action-group">
            <button type="button" class="wb-action-btn" data-wb-builder-item-move="up" title="Move up" aria-label="Move up"><i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i></button>
            <button type="button" class="wb-action-btn" data-wb-builder-item-move="down" title="Move down" aria-label="Move down"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
            <button type="button" class="wb-action-btn" data-wb-builder-item-toggle title="Collapse item" aria-label="Collapse item"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
            <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-builder-item-remove title="Remove item" aria-label="Remove item"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
        </div>
    </div>

    <div class="wb-card-body wb-stack wb-gap-3" data-wb-builder-item-body="{{ $editorKey }}">
        <input type="hidden" name="{{ $rowPrefix }}[id]" value="{{ is_numeric($index) ? $columnItem->id : '' }}">
        <input type="hidden" name="{{ $rowPrefix }}[block_type_id]" value="{{ $itemBlockType?->id }}">
        <input type="hidden" name="{{ $rowPrefix }}[sort_order]" value="{{ $rowSortOrder }}" data-wb-builder-item-sort="{{ $editorKey }}">
        <input type="hidden" name="{{ $rowPrefix }}[_delete]" value="0" data-wb-builder-item-delete="{{ $editorKey }}">

        <div class="wb-grid wb-grid-2">
            <div class="wb-stack wb-gap-1">
                <label>{{ $titleLabel }}</label>
                <input class="wb-input" type="text" name="{{ $rowPrefix }}[title]" value="{{ $columnItem->title }}" @if ($titlePlaceholder) placeholder="{{ $titlePlaceholder }}" @endif data-wb-builder-item-title="{{ $editorKey }}">
            </div>

            <div class="wb-stack wb-gap-1">
                <label>{{ $urlLabel }}</label>
                <input class="wb-input" type="text" name="{{ $rowPrefix }}[url]" value="{{ $columnItem->url }}">
            </div>
        </div>

        @if ($showSubtitle)
            <div class="wb-stack wb-gap-1">
                <label>{{ $subtitleLabel }}</label>
                <input class="wb-input" type="text" name="{{ $rowPrefix }}[subtitle]" value="{{ $columnItem->subtitle }}" @if ($subtitlePlaceholder) placeholder="{{ $subtitlePlaceholder }}" @endif>
            </div>
        @endif

        <div class="wb-stack wb-gap-1">
            <label>{{ $contentLabel }}</label>
            <textarea class="wb-textarea" rows="4" name="{{ $rowPrefix }}[content]">{{ $columnItem->content }}</textarea>
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
