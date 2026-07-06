@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.fallback.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-warning">
        <div>
            <div class="wb-alert-title">{{ $adminText('title') }}</div>
            <div>{{ $adminText('description') }}</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">{{ $adminText('field_title') }}</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="subtitle">{{ $adminText('subtitle') }}</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">{{ $adminText('content') }}</label>
        <textarea id="content" name="content" class="wb-textarea" rows="6">{{ old('content', $block->content) }}</textarea>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="url">{{ $adminText('url') }}</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $block->url) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label>{{ $adminText('media_asset') }}</label>
            @include('webblocks-cms::admin.media.asset-picker-panel', [
                'name' => 'fallback-asset',
                'inputId' => 'asset_id',
                'fieldName' => 'asset_id',
                'selectedAsset' => old('asset_id') ? null : ($selectedAsset ?? $block->asset),
                'buttonLabel' => $adminText('choose_from_media'),
                'replaceLabel' => $adminText('replace_media'),
                'clearLabel' => $adminText('remove'),
            ])
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="variant">{{ $adminText('variant') }}</label>
            <input id="variant" name="variant" class="wb-input" type="text" value="{{ old('variant', $block->variant) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="meta">{{ $adminText('meta') }}</label>
            <textarea id="meta" name="meta" class="wb-textarea" rows="3">{{ old('meta', $block->meta) }}</textarea>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="settings">{{ $adminText('settings') }}</label>
        <textarea id="settings" name="settings" class="wb-textarea" rows="4">{{ old('settings', $block->settingsText()) }}</textarea>
    </div>
</div>
