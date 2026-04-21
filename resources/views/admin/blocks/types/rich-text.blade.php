<div class="wb-stack wb-gap-1">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Rich text content is translated per locale.</div>
        </div>
    @endif

    <label for="content">Rich Text Content</label>
    <textarea id="content" name="content" class="wb-textarea" rows="10" required>{{ old('content', $block->content) }}</textarea>
    <span>A plain textarea is currently used here while preserving the rich text intent.</span>
</div>
