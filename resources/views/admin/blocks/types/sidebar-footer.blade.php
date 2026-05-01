@php
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Footer title, body, and version text are translated per locale. Callout variant stays shared.</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Callout Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="sidebar_footer_variant">Callout Variant</label>
            <select id="sidebar_footer_variant" name="sidebar_footer_variant" class="wb-select">
                @foreach (['info' => 'Info', 'success' => 'Success', 'warning' => 'Warning', 'danger' => 'Danger'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('sidebar_footer_variant', $settings['variant'] ?? 'info') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Callout Body</label>
        <textarea id="content" name="content" class="wb-textarea" rows="5">{{ old('content', $block->content) }}</textarea>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="subtitle">Footer Text</label>
        <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}" placeholder="WebBlocks UI v2.4.4">
    </div>
</div>
