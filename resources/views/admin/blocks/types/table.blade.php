<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Table heading and cell text are translated per locale. Header-row behavior stays shared across locales.</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Table Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="variant">Table Style</label>
            <select id="variant" name="variant" class="wb-select">
                <option value="header-row" @selected(old('variant', $block->variant ?: 'header-row') === 'header-row')>First row is header</option>
                <option value="plain" @selected(old('variant', $block->variant) === 'plain')>All rows are body rows</option>
            </select>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Table Rows</label>
        <textarea id="content" name="content" class="wb-textarea" rows="8" placeholder="Plan | Seats | Support&#10;Starter | 3 | Email&#10;Scale | 10 | Priority">{{ old('content', $block->content) }}</textarea>
        <div class="wb-text-sm wb-text-muted">Enter one row per line. Separate cells with a vertical bar: <code>|</code>.</div>
    </div>
</div>
