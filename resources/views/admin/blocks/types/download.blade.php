<div class="wb-stack wb-gap-4">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Download Label</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="subtitle">Helper Text</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label>Document Asset</label>
        @include('admin.media.asset-picker-panel', [
            'name' => 'download-asset',
            'inputId' => 'asset_id',
            'fieldName' => 'asset_id',
            'selectedAsset' => old('asset_id') ? null : ($selectedAsset ?? $block->downloadAsset()),
            'buttonLabel' => 'Choose from Media',
            'replaceLabel' => 'Replace Document',
            'clearLabel' => 'Remove',
            'accept' => 'document',
        ])
        <span>Choose an internal document asset for this download block.</span>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="variant">Variant</label>
        <select id="variant" name="variant" class="wb-select">
            @foreach (['primary', 'secondary', 'ghost'] as $variant)
                <option value="{{ $variant }}" @selected(old('variant', $block->variant ?: 'secondary') === $variant)>{{ $variant }}</option>
            @endforeach
        </select>
    </div>
</div>
