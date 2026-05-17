<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Title and supporting copy are translated per locale. The selected Media item and external video URL stay shared across locales.</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Video Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="url">External Video URL</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $block->url) }}" placeholder="https://www.youtube.com/watch?v=...">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Supporting Copy</label>
        <textarea id="content" name="content" class="wb-textarea" rows="4">{{ old('content', $block->content) }}</textarea>
    </div>

    @include('admin.media.asset-picker-panel', [
        'name' => 'video-asset',
        'inputId' => 'asset_id',
        'fieldName' => 'asset_id',
        'selectedAsset' => old('asset_id') ? null : ($selectedAsset ?? $block->asset),
        'buttonLabel' => 'Choose from Media',
        'replaceLabel' => 'Replace Video',
        'clearLabel' => 'Remove',
        'accept' => 'video',
        'panelMode' => 'overlay',
        'panelTitle' => 'Choose Video',
        'compactControls' => true,
        'resultsVariant' => 'compact-list',
        'showUpload' => false,
        'selectorCard' => true,
        'selectorCardTitle' => 'Hosted Video',
        'selectorHelperText' => 'Select a hosted Media video or leave it empty and use an external video URL.',
    ])
</div>
