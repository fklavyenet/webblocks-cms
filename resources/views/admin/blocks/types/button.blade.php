<div class="wb-stack wb-gap-4">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Button Label</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="url">URL</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $block->url) }}" required>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="subtitle">Target</label>
            <select id="subtitle" name="subtitle" class="wb-select">
                <option value="_self" @selected(old('subtitle', $block->subtitle ?: '_self') === '_self')>Same tab</option>
                <option value="_blank" @selected(old('subtitle', $block->subtitle) === '_blank')>New tab</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="variant">Variant</label>
            <select id="variant" name="variant" class="wb-select">
                @foreach (['primary', 'secondary', 'ghost', 'danger'] as $variant)
                    <option value="{{ $variant }}" @selected(old('variant', $block->variant ?: 'primary') === $variant)>{{ $variant }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
