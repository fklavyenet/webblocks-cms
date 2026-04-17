<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-warning">
        <div>
            <div class="wb-alert-title">Generic Block Form</div>
            <div>No custom inline admin form partial was found for this block type. The safe fallback form is being used.</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_title">Title</label>
            <input id="block_{{ $index }}_title" name="{{ $prefix }}[title]" class="wb-input" type="text" value="{{ old("{$prefix}.title", $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_subtitle">Subtitle</label>
            <input id="block_{{ $index }}_subtitle" name="{{ $prefix }}[subtitle]" class="wb-input" type="text" value="{{ old("{$prefix}.subtitle", $block->subtitle) }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_content">Content</label>
        <textarea id="block_{{ $index }}_content" name="{{ $prefix }}[content]" class="wb-textarea" rows="6">{{ old("{$prefix}.content", $block->content) }}</textarea>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_url">URL</label>
            <input id="block_{{ $index }}_url" name="{{ $prefix }}[url]" class="wb-input" type="text" value="{{ old("{$prefix}.url", $block->url) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_asset_id">Media Asset ID</label>
            <input id="block_{{ $index }}_asset_id" name="{{ $prefix }}[asset_id]" class="wb-input" type="number" min="1" value="{{ old("{$prefix}.asset_id", $block->asset_id) }}">
        </div>
    </div>
</div>
