<div class="wb-grid wb-grid-2">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_title">Button Label</label>
        <input id="block_{{ $index }}_title" name="{{ $prefix }}[title]" class="wb-input" type="text" value="{{ old("{$prefix}.title", $block->title) }}" required>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_url">URL</label>
        <input id="block_{{ $index }}_url" name="{{ $prefix }}[url]" class="wb-input" type="text" value="{{ old("{$prefix}.url", $block->url) }}" required>
    </div>
</div>

<div class="wb-grid wb-grid-2">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_subtitle">Target</label>
        <select id="block_{{ $index }}_subtitle" name="{{ $prefix }}[subtitle]" class="wb-select">
            <option value="_self" @selected(old("{$prefix}.subtitle", $block->subtitle ?: '_self') === '_self')>Same tab</option>
            <option value="_blank" @selected(old("{$prefix}.subtitle", $block->subtitle) === '_blank')>New tab</option>
        </select>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_variant">Variant</label>
        <select id="block_{{ $index }}_variant" name="{{ $prefix }}[variant]" class="wb-select">
            @foreach (['primary', 'secondary', 'ghost', 'danger'] as $variant)
                <option value="{{ $variant }}" @selected(old("{$prefix}.variant", $block->variant ?: 'primary') === $variant)>{{ $variant }}</option>
            @endforeach
        </select>
    </div>
</div>
