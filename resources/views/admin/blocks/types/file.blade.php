<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Title and supporting copy are translated per locale. The selected Media item and external URL stay shared across locales.</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">File Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="url">External File URL</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $block->url) }}" placeholder="https://example.com/file.pdf">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Supporting Copy</label>
        <textarea id="content" name="content" class="wb-textarea" rows="4">{{ old('content', $block->content) }}</textarea>
    </div>

    <div class="wb-stack wb-gap-1">
        <label>Media File</label>
        @include('admin.media.asset-picker-panel', [
            'name' => 'file-asset',
            'inputId' => 'asset_id',
            'fieldName' => 'asset_id',
            'selectedAsset' => old('asset_id') ? null : ($selectedAsset ?? $block->asset),
            'buttonLabel' => 'Choose from Media',
            'replaceLabel' => 'Replace File',
            'clearLabel' => 'Remove',
            'accept' => 'file',
            'panelMode' => 'overlay',
            'panelTitle' => 'Choose File',
            'compactControls' => true,
            'resultsVariant' => 'compact-list',
            'showUpload' => false,
        ])
        <span>Select a Media file for the canonical file source, or leave it empty and use an external file URL.</span>
    </div>
</div>
