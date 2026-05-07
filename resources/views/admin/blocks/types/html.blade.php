<div class="wb-stack wb-gap-1">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Trusted HTML content is translated per locale.</div>
        </div>
    @endif

    <div class="wb-alert wb-alert-warning">
        <div><strong>Trusted HTML only.</strong> This block renders stored HTML markup directly. Use Rich Text for normal formatted copy and Code for escaped snippets. Do not paste scripts, event attributes, or untrusted third-party markup.</div>
    </div>

    <label for="content">Trusted HTML</label>
    <textarea id="content" name="content" class="wb-textarea" rows="10" required>{{ old('content', $block->content) }}</textarea>
</div>
