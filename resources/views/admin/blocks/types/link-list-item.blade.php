<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Title, meta, and description are translated per locale. URL stays shared across locales.</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Link Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="url">URL</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $block->url) }}" required>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="subtitle">Meta</label>
        <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}" required>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Description</label>
        <textarea id="content" name="content" class="wb-textarea" rows="6" required>{{ old('content', $block->content) }}</textarea>
    </div>
</div>
