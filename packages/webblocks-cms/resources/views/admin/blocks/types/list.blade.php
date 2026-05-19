<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>List heading and items are translated per locale. List style stays shared across locales.</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">List Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="variant">List Style</label>
            <select id="variant" name="variant" class="wb-select">
                <option value="unordered" @selected(old('variant', $block->variant ?: 'unordered') === 'unordered')>Bulleted list</option>
                <option value="ordered" @selected(old('variant', $block->variant) === 'ordered')>Numbered list</option>
            </select>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">List Items</label>
        <textarea id="content" name="content" class="wb-textarea" rows="8" placeholder="First item&#10;Second item&#10;Third item">{{ old('content', $block->content) }}</textarea>
        <div class="wb-text-sm wb-text-muted">Enter one item per line.</div>
    </div>
</div>
