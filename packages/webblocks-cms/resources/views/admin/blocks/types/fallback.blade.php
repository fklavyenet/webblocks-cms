<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-warning">
        <div>
            <div class="wb-alert-title">Generic Block Form</div>
            <div>No custom admin form partial was found for this block type. The safe fallback form is being used.</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="subtitle">Subtitle</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Content</label>
        <textarea id="content" name="content" class="wb-textarea" rows="6">{{ old('content', $block->content) }}</textarea>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="url">URL</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $block->url) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label>Media Asset</label>
            @include('admin.media.asset-picker-panel', [
                'name' => 'fallback-asset',
                'inputId' => 'asset_id',
                'fieldName' => 'asset_id',
                'selectedAsset' => old('asset_id') ? null : ($selectedAsset ?? $block->asset),
                'buttonLabel' => 'Choose from Media',
                'replaceLabel' => 'Replace Media',
                'clearLabel' => 'Remove',
            ])
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="variant">Variant</label>
            <input id="variant" name="variant" class="wb-input" type="text" value="{{ old('variant', $block->variant) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="meta">Meta</label>
            <textarea id="meta" name="meta" class="wb-textarea" rows="3">{{ old('meta', $block->meta) }}</textarea>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="settings">Settings</label>
        <textarea id="settings" name="settings" class="wb-textarea" rows="4">{{ old('settings', $block->settingsText()) }}</textarea>
    </div>
</div>
