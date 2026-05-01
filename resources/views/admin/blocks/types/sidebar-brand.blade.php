@php
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Brand title and subtitle are translated per locale. URL and target stay shared across locales.</div>
        </div>
    @endif

    <div class="wb-alert wb-alert-info">
        <div>This block renders only the inner <code>wb-sidebar-brand</code> link. The outer <code>aside.wb-sidebar</code> wrapper still belongs to the Docs Sidebar slot preset.</div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Brand Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="url">URL</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $settings['url'] ?? $block->url) }}" placeholder="/" required>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="subtitle">Subtitle</label>
        <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="target">Target</label>
        <select id="target" name="target" class="wb-select">
            <option value="_self" @selected(old('target', $settings['target'] ?? '_self') === '_self')>Same tab</option>
            <option value="_blank" @selected(old('target', $settings['target'] ?? '_self') === '_blank')>New tab</option>
        </select>
    </div>
</div>
