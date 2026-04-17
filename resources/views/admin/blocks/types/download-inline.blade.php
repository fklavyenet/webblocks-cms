<div class="wb-grid wb-grid-2">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_title">Download Label</label>
        <input id="block_{{ $index }}_title" name="{{ $prefix }}[title]" class="wb-input" type="text" value="{{ old("{$prefix}.title", $block->title) }}" required>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_subtitle">Helper Text</label>
        <input id="block_{{ $index }}_subtitle" name="{{ $prefix }}[subtitle]" class="wb-input" type="text" value="{{ old("{$prefix}.subtitle", $block->subtitle) }}">
    </div>
</div>

<div class="wb-grid wb-grid-2">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_asset_id">Document Asset ID</label>
        <input id="block_{{ $index }}_asset_id" name="{{ $prefix }}[asset_id]" class="wb-input" type="number" min="1" value="{{ old("{$prefix}.asset_id", $block->asset_id) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_variant">Variant</label>
        <select id="block_{{ $index }}_variant" name="{{ $prefix }}[variant]" class="wb-select">
            @foreach (['primary', 'secondary', 'ghost'] as $variant)
                <option value="{{ $variant }}" @selected(old("{$prefix}.variant", $block->variant ?: 'secondary') === $variant)>{{ $variant }}</option>
            @endforeach
        </select>
    </div>
</div>
