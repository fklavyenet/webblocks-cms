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

    <div class="wb-stack wb-gap-2">
        <label class="wb-cluster wb-cluster-2 wb-items-center" for="image_viewer_enabled">
            <input id="image_viewer_enabled" name="image_viewer_enabled" type="hidden" value="0">
            <input id="image_viewer_enabled" name="image_viewer_enabled" type="checkbox" value="1" @checked((bool) old('image_viewer_enabled', $block->setting('viewer_enabled', false)))>
            <span>{{ $adminText('viewer_enabled_label') }}</span>
        </label>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('viewer_enabled_help') }}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="image_viewer_group">{{ $adminText('viewer_group_label') }}</label>
        <input
            id="image_viewer_group"
            name="image_viewer_group"
            class="wb-input"
            type="text"
            value="{{ old('image_viewer_group', $block->setting('viewer_group', 'page-images')) }}"
            placeholder="page-images"
        >
        <div class="wb-text-sm wb-text-muted">{{ $adminText('viewer_group_help') }}</div>
    </div>
</div>
