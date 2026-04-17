<div class="wb-stack wb-gap-4">
    <div class="wb-stack wb-gap-1">
        <label>Media Asset</label>
        @include('admin.media.asset-picker-panel', [
            'name' => 'image-asset',
            'inputId' => 'asset_id',
            'fieldName' => 'asset_id',
            'selectedAsset' => old('asset_id') ? null : ($selectedAsset ?? $block->asset),
            'buttonLabel' => 'Choose from Media',
            'replaceLabel' => 'Replace Image',
            'clearLabel' => 'Remove',
            'accept' => 'image',
        ])
        <span>Choose an internal image asset for this block.</span>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="subtitle">Alt Text</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="url">Link URL</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $block->url) }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="title">Caption</label>
        <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
    </div>
</div>
