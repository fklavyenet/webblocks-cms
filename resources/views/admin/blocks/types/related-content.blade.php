<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Section title, subtitle, and intro are translated per locale. Add child <code>button</code> or <code>column_item</code> blocks for reusable related links.</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Section Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="subtitle">Section Subtitle</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Section Intro</label>
        <textarea id="content" name="content" class="wb-textarea" rows="5" placeholder="Point readers to the next useful pages, guides, or references.">{{ old('content', $block->content) }}</textarea>
        <div class="wb-text-sm wb-text-muted">Preferred structure: add child <code>button</code> or <code>column_item</code> blocks beneath this block. Legacy line-delimited link content still renders for compatibility.</div>
    </div>
</div>
