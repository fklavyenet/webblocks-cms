<div class="wb-stack wb-gap-1">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Text content is translated per locale.</div>
        </div>
    @endif

    <label for="content">Text Content</label>
    <textarea id="content" name="content" class="wb-textarea" rows="8" required>{{ old('content', $block->content) }}</textarea>
</div>
