<div class="wb-grid wb-grid-2">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_asset_id">Image Asset ID</label>
        <input id="block_{{ $index }}_asset_id" name="{{ $prefix }}[asset_id]" class="wb-input" type="number" min="1" value="{{ old("{$prefix}.asset_id", $block->asset_id) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_subtitle">Alt Text</label>
        <input id="block_{{ $index }}_subtitle" name="{{ $prefix }}[subtitle]" class="wb-input" type="text" value="{{ old("{$prefix}.subtitle", $block->subtitle) }}">
    </div>
</div>

<div class="wb-grid wb-grid-2">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_title">Caption</label>
        <input id="block_{{ $index }}_title" name="{{ $prefix }}[title]" class="wb-input" type="text" value="{{ old("{$prefix}.title", $block->title) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_url">Link URL</label>
        <input id="block_{{ $index }}_url" name="{{ $prefix }}[url]" class="wb-input" type="text" value="{{ old("{$prefix}.url", $block->url) }}">
    </div>
</div>
