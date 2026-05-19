<div class="wb-stack wb-gap-4">
    <div class="wb-stack wb-gap-1">
        <label for="variant">Quote Variant</label>
        <select id="variant" name="variant" class="wb-select">
            <option value="default" @selected(old('variant', $block->variant ?: 'default') === 'default')>Default quote</option>
            <option value="testimonial" @selected(old('variant', $block->variant) === 'testimonial')>Testimonial</option>
        </select>
        <div class="wb-text-sm wb-text-muted">Use Testimonial when the quote should render as a framed social-proof card.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Quote Text</label>
        <textarea id="content" name="content" class="wb-textarea" rows="6" required>{{ old('content', $block->content) }}</textarea>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Author</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="subtitle">Source</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
        </div>
    </div>
</div>
