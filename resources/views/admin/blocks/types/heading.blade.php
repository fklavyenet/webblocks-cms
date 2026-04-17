<div class="wb-stack wb-gap-4">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Heading Text</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="variant">Heading Level</label>
            <select id="variant" name="variant" class="wb-select">
                @foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $level)
                    <option value="{{ $level }}" @selected(old('variant', $block->variant ?: 'h2') === $level)>{{ strtoupper($level) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="url">Anchor ID</label>
        <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $block->url) }}">
    </div>
</div>
