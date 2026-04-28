<div class="wb-stack wb-gap-1">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Plain text content is translated per locale.</div>
        </div>
    @endif

    <label for="text">Text</label>
    <textarea id="text" name="text" class="wb-textarea" rows="8" required>{{ old('text', $block->content) }}</textarea>
</div>
