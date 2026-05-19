<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Alert title and body copy are translated per locale. Alert variant stays shared across locales.</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label for="title">Title</label>
        <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Content</label>
        <textarea id="content" name="content" class="wb-textarea" rows="4" required>{{ old('content', $block->content) }}</textarea>
    </div>
</div>
