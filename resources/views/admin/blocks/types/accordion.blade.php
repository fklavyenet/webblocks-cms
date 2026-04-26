<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Accordion heading is translated per locale. Child item structure stays shared.</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label for="title">Accordion Title</label>
        <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Intro Text</label>
        <textarea id="content" name="content" class="wb-textarea" rows="4">{{ old('content', $block->content) }}</textarea>
        <div class="wb-text-sm wb-text-muted">Add child FAQ or content blocks as accordion items.</div>
    </div>
</div>
