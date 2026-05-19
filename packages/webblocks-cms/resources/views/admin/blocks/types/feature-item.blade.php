<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Feature title and text are translated per locale. Optional link stays shared across locales.</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Feature Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="url">Optional Link</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $block->url) }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Feature Text</label>
        <textarea id="content" name="content" class="wb-textarea" rows="6" required>{{ old('content', $block->content) }}</textarea>
    </div>
</div>
