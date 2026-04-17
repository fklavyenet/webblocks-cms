<div class="wb-grid wb-grid-2">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_title">Heading Text</label>
        <input id="block_{{ $index }}_title" name="{{ $prefix }}[title]" class="wb-input" type="text" value="{{ old("{$prefix}.title", $block->title) }}" required>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_variant">Heading Level</label>
        <select id="block_{{ $index }}_variant" name="{{ $prefix }}[variant]" class="wb-select">
            @foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $level)
                <option value="{{ $level }}" @selected(old("{$prefix}.variant", $block->variant ?: 'h2') === $level)>{{ strtoupper($level) }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="wb-stack wb-gap-1">
    <label for="block_{{ $index }}_url">Anchor ID</label>
    <input id="block_{{ $index }}_url" name="{{ $prefix }}[url]" class="wb-input" type="text" value="{{ old("{$prefix}.url", $block->url) }}">
</div>
