@php
    $metaItems = old('meta_items', $block->metaItems()->all());
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Title, intro text, and meta items are translated per locale. Title level and alignment stay shared across locales.</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="title_level">Title level</label>
            <select id="title_level" name="title_level" class="wb-select" required>
                @foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $level)
                    <option value="{{ $level }}" @selected(old('title_level', $block->variant ?: 'h1') === $level)>{{ strtoupper($level) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="intro_text">Intro text</label>
        <textarea id="intro_text" name="intro_text" class="wb-textarea" rows="4">{{ old('intro_text', $block->subtitle) }}</textarea>
    </div>

    <div class="wb-card wb-card-muted" data-wb-builder-items-editor="content-header-meta-items">
        <div class="wb-card-header wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
            <strong>Meta items</strong>
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-builder-item-add="content-header-meta-items">Add item</button>
        </div>
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-text-sm wb-text-muted">Optional translated metadata items rendered with WebBlocks UI dividers between entries.</div>

            <div class="wb-stack wb-gap-3" data-wb-builder-item-list="content-header-meta-items">
                @forelse ($metaItems as $index => $metaItem)
                    <div class="wb-card" data-wb-builder-item-row="content-header-meta-items">
                        <div class="wb-card-header wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                            <strong data-wb-builder-item-label="content-header-meta-items">{{ trim((string) $metaItem) !== '' ? $metaItem : 'New Item' }}</strong>
                            <div class="wb-flex wb-items-center wb-gap-2">
                                <button type="button" class="wb-action-btn" data-wb-builder-item-move="up" title="Move up" aria-label="Move up"><i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i></button>
                                <button type="button" class="wb-action-btn" data-wb-builder-item-move="down" title="Move down" aria-label="Move down"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
                                <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-builder-item-remove title="Remove item" aria-label="Remove item"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                            </div>
                        </div>
                        <div class="wb-card-body wb-stack wb-gap-3" data-wb-builder-item-body="content-header-meta-items">
                            <div class="wb-stack wb-gap-1">
                                <label for="meta_items_{{ $index }}">Item</label>
                                <input id="meta_items_{{ $index }}" class="wb-input" type="text" name="meta_items[]" value="{{ $metaItem }}" data-wb-builder-item-title="content-header-meta-items">
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="wb-empty" data-wb-builder-item-empty="content-header-meta-items">
                        <div class="wb-empty-title">No items yet</div>
                        <div class="wb-empty-text">Add the first meta item to continue.</div>
                    </div>
                @endforelse
            </div>
        </div>

        <template
            data-wb-builder-item-template="content-header-meta-items"
            data-empty-title="No items yet"
            data-empty-description="Add the first meta item to continue."
        >
            <div class="wb-card" data-wb-builder-item-row="content-header-meta-items">
                <div class="wb-card-header wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                    <strong data-wb-builder-item-label="content-header-meta-items">New Item</strong>
                    <div class="wb-flex wb-items-center wb-gap-2">
                        <button type="button" class="wb-action-btn" data-wb-builder-item-move="up" title="Move up" aria-label="Move up"><i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i></button>
                        <button type="button" class="wb-action-btn" data-wb-builder-item-move="down" title="Move down" aria-label="Move down"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
                        <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-builder-item-remove title="Remove item" aria-label="Remove item"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div class="wb-card-body wb-stack wb-gap-3" data-wb-builder-item-body="content-header-meta-items">
                    <div class="wb-stack wb-gap-1">
                        <label for="meta_items___INDEX__">Item</label>
                        <input id="meta_items___INDEX__" class="wb-input" type="text" name="meta_items[]" value="" data-wb-builder-item-title="content-header-meta-items">
                    </div>
                </div>
            </div>
        </template>

        <input type="hidden" data-wb-builder-item-next-index="content-header-meta-items" value="{{ count($metaItems) }}">
    </div>
</div>
