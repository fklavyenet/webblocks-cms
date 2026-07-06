@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.audio.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    @include('webblocks-cms::admin.media.asset-picker-panel', [
        'name' => 'audio-asset',
        'inputId' => 'asset_id',
        'fieldName' => 'asset_id',
        'selectedAsset' => old('asset_id') ? null : ($selectedAsset ?? $block->asset),
        'buttonLabel' => $adminText('choose_media'),
        'replaceLabel' => $adminText('replace_audio'),
        'clearLabel' => $adminText('remove'),
        'accept' => 'audio',
        'panelMode' => 'overlay',
        'panelTitle' => $adminText('choose_audio'),
        'compactControls' => true,
        'resultsVariant' => 'compact-list',
        'showUpload' => false,
        'selectorCard' => true,
        'selectorCardTitle' => $adminText('audio_card_title'),
        'selectorHelperText' => $adminText('audio_card_help'),
    ])

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">{{ $adminText('title_label') }}</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="url">{{ $adminText('url_label') }}</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $block->url) }}" placeholder="https://example.com/audio.mp3">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">{{ $adminText('content_label') }}</label>
        <textarea id="content" name="content" class="wb-textarea" rows="4">{{ old('content', $block->content) }}</textarea>
    </div>
</div>
