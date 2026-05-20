@php
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Brand title and subtitle are translated per locale. URL, target, and logo stay shared across locales.</div>
        </div>
    @endif

    <div class="wb-alert wb-alert-info">
        <div>This block renders only the inner <code>wb-sidebar-brand</code> link. The outer <code>aside.wb-sidebar</code> wrapper still belongs to the docs public shell when the block lives in the Sidebar slot.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label>Logo</label>
        @include('webblocks-cms::admin.media.asset-picker-panel', [
            'name' => 'sidebar-brand-logo',
            'inputId' => 'asset_id',
            'fieldName' => 'asset_id',
            'selectedAsset' => old('asset_id') ? null : ($selectedAsset ?? $block->asset),
            'buttonLabel' => 'Choose from Media',
            'replaceLabel' => 'Replace Logo',
            'clearLabel' => 'Remove',
            'accept' => 'image',
        ])
        <span>Upload the logo in Media, then select it here.</span>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Brand Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
            <div class="wb-text-sm wb-text-muted">Optional when a logo is present.</div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="url">URL</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $settings['url'] ?? $block->url) }}" placeholder="Falls back to the site home URL">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="subtitle">Subtitle</label>
        <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="sidebar_brand_aria_label">Accessible Label</label>
        <input id="sidebar_brand_aria_label" name="sidebar_brand_aria_label" class="wb-input" type="text" value="{{ old('sidebar_brand_aria_label', $settings['aria_label'] ?? '') }}">
        <div class="wb-text-sm wb-text-muted">Used for logo-only brands when visible title text is empty. Falls back to the site label if left blank.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="target">Target</label>
        <select id="target" name="target" class="wb-select">
            <option value="_self" @selected(old('target', $settings['target'] ?? '_self') === '_self')>Same tab</option>
            <option value="_blank" @selected(old('target', $settings['target'] ?? '_self') === '_blank')>New tab</option>
        </select>
    </div>
</div>
