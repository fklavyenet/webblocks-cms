<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Title and supporting copy are translated per locale. The selected Media item and external audio URL stay shared across locales.</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Audio Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="url">External Audio URL</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $block->url) }}" placeholder="https://example.com/audio.mp3">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Supporting Copy</label>
        <textarea id="content" name="content" class="wb-textarea" rows="4">{{ old('content', $block->content) }}</textarea>
    </div>

    @include('admin.media.asset-picker-panel', [
        'name' => 'audio-asset',
        'inputId' => 'asset_id',
        'fieldName' => 'asset_id',
        'selectedAsset' => old('asset_id') ? null : ($selectedAsset ?? $block->asset),
        'buttonLabel' => 'Choose from Media',
        'replaceLabel' => 'Replace Audio',
        'clearLabel' => 'Remove',
        'accept' => 'audio',
        'panelMode' => 'overlay',
        'panelTitle' => 'Choose Audio',
        'compactControls' => true,
        'resultsVariant' => 'compact-list',
        'showUpload' => false,
        'selectorCard' => true,
        'selectorCardTitle' => 'Hosted Audio',
        'selectorHelperText' => 'Select a Media audio file or leave it empty and use an external audio URL.',
    ])
</div>
