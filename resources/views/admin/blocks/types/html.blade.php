<div class="wb-stack wb-gap-1">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>HTML content is translated per locale.</div>
        </div>
    @endif

    <label for="content">Raw HTML</label>
    <textarea id="content" name="content" class="wb-textarea" rows="10" required>{{ old('content', $block->content) }}</textarea>
</div>
