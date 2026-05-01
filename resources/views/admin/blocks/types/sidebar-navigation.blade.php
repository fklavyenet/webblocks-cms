@php
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Navigation ARIA label is translated per locale. Child item structure and shared settings stay shared.</div>
        </div>
    @endif

    <div class="wb-alert wb-alert-info">
        <div>This block renders only <code>nav.wb-sidebar-nav</code> and one inner <code>div.wb-sidebar-section</code>. It accepts Sidebar Nav Item and Sidebar Nav Group children only.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="title">Navigation ARIA Label</label>
        <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title ?: 'Documentation navigation') }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="name">Admin Label</label>
        <input id="name" name="name" class="wb-input" type="text" value="{{ old('name', $settings['layout_name'] ?? '') }}" placeholder="Docs navigation">
        <div class="wb-text-sm wb-text-muted">Editor-only label for tree navigation. Not rendered publicly.</div>
    </div>
</div>
