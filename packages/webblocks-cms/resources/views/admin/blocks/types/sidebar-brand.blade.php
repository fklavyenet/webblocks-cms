@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.sidebar_brand.'.$key, $adminLocale);
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    <div class="wb-alert wb-alert-info">
        <div>{!! $adminText('system_help') !!}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label>{{ $adminText('logo_label') }}</label>
        @include('webblocks-cms::admin.media.asset-picker-panel', [
            'name' => 'sidebar-brand-logo',
            'inputId' => 'asset_id',
            'fieldName' => 'asset_id',
            'selectedAsset' => old('asset_id') ? null : ($selectedAsset ?? $block->asset),
            'buttonLabel' => $adminText('choose_media'),
            'replaceLabel' => $adminText('replace_logo'),
            'clearLabel' => $adminText('remove'),
            'accept' => 'image',
        ])
        <span>{{ $adminText('logo_help') }}</span>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">{{ $adminText('title_label') }}</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
            <div class="wb-text-sm wb-text-muted">{{ $adminText('title_help') }}</div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="url">{{ $adminText('url_label') }}</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $settings['url'] ?? $block->url) }}" placeholder="{{ $adminText('url_placeholder') }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="subtitle">{{ $adminText('subtitle_label') }}</label>
        <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="sidebar_brand_aria_label">{{ $adminText('aria_label') }}</label>
        <input id="sidebar_brand_aria_label" name="sidebar_brand_aria_label" class="wb-input" type="text" value="{{ old('sidebar_brand_aria_label', $settings['aria_label'] ?? '') }}">
        <div class="wb-text-sm wb-text-muted">{{ $adminText('aria_help') }}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="target">{{ $adminText('target_label') }}</label>
        <select id="target" name="target" class="wb-select">
            <option value="_self" @selected(old('target', $settings['target'] ?? '_self') === '_self')>{{ $adminText('same_tab') }}</option>
            <option value="_blank" @selected(old('target', $settings['target'] ?? '_self') === '_blank')>{{ $adminText('new_tab') }}</option>
        </select>
    </div>
</div>
