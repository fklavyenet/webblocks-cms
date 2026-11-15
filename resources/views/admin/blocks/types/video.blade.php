@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.video.'.$key, $adminLocale);
    $captionLocale = $activeLocale->code ?? app(\WebBlocks\Cms\Support\Locales\LocaleResolver::class)->default()->code;
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    @include('webblocks-cms::admin.media.asset-picker-panel', [
        'name' => 'video-asset',
        'inputId' => 'asset_id',
        'fieldName' => 'asset_id',
        'selectedAsset' => session()->hasOldInput('asset_id') ? ($selectedAsset ?? null) : ($selectedAsset ?? $block->asset),
        'buttonLabel' => $adminText('choose_media'),
        'replaceLabel' => $adminText('replace_video'),
        'clearLabel' => $adminText('remove'),
        'accept' => 'video',
        'panelMode' => 'overlay',
        'panelTitle' => $adminText('choose_video'),
        'compactControls' => true,
        'resultsVariant' => 'compact-list',
        'showUpload' => false,
        'selectorCard' => true,
        'selectorCardTitle' => $adminText('video_card_title'),
        'selectorHelperText' => $adminText('video_card_help'),
    ])

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">{{ $adminText('title_label') }}</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="url">{{ $adminText('url_label') }}</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $block->url) }}" placeholder="https://www.youtube.com/watch?v=...">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="caption_path">{{ $adminText('caption_path') }} ({{ strtoupper($captionLocale) }})</label>
        <input id="caption_path" name="caption_path" class="wb-input" type="text" value="{{ old('caption_path', $block->setting('caption_tracks.'.$captionLocale)) }}" placeholder="/storage/media/documents/example-en.vtt" aria-describedby="caption-path-help">
        <p id="caption-path-help" class="wb-text-sm wb-text-muted">{{ $adminText('captions_help') }}</p>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">{{ $adminText('content_label') }}</label>
        <textarea id="content" name="content" class="wb-textarea" rows="4">{{ old('content', $block->content) }}</textarea>
    </div>
</div>
