<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Stat card eyebrow or label, value, and description are translated per locale. Optional URL stays shared across locales. Value is stored as translated string content, so values like 0, 6, 14+, and 173 are valid.</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label for="subtitle">Eyebrow / Label</label>
        <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="title">Value</label>
        <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
        <div class="wb-text-sm wb-text-muted">Translated string value. This may be 0, 6, 14+, 173, or any other display-ready metric.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Description</label>
        <textarea id="content" name="content" class="wb-textarea" rows="4">{{ old('content', $block->content) }}</textarea>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="url">Optional URL</label>
        <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $block->url) }}">
    </div>
</div>
