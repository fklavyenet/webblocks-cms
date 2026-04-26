<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Section title, intro, and editorial links are translated per locale.</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Section Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="subtitle">Intro</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Related Links</label>
        <textarea id="content" name="content" class="wb-textarea" rows="8" placeholder="Getting Started | /docs/start | Guide | Basics and setup&#10;API Reference | /docs/api | Docs | Endpoints and payloads">{{ old('content', $block->content) }}</textarea>
        <div class="wb-text-sm wb-text-muted">Enter one link per line as <code>Title | URL | Optional meta | Optional description</code>. Leave blank to fall back to automatic related pages.</div>
    </div>
</div>
