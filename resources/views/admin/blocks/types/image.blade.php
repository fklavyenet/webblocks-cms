@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.image.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    @include('webblocks-cms::admin.media.asset-picker-panel', [
        'name' => 'image-asset',
        'inputId' => 'asset_id',
        'fieldName' => 'asset_id',
        'selectedAsset' => old('asset_id') ? null : ($selectedAsset ?? $block->asset),
        'buttonLabel' => $adminText('choose_media'),
        'replaceLabel' => $adminText('replace_image'),
        'clearLabel' => $adminText('remove'),
        'accept' => 'image',
        'panelMode' => 'overlay',
        'panelTitle' => $adminText('choose_image'),
        'compactControls' => true,
        'resultsVariant' => 'compact-list',
        'showUpload' => false,
        'selectorCard' => true,
        'selectorCardTitle' => $adminText('asset_title'),
        'selectorHelperText' => $adminText('asset_help'),
    ])

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="subtitle">{{ $adminText('alt_label') }}</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="url">{{ $adminText('url_label') }}</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $block->url) }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="title">{{ $adminText('caption_label') }}</label>
        <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
    </div>
</div>
