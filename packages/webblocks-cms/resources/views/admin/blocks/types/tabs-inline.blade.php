<div class="wb-stack wb-gap-4">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_title">Tab Label</label>
            <input id="block_{{ $index }}_title" name="{{ $prefix }}[title]" class="wb-input" type="text" value="{{ old("{$prefix}.title", $block->title) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_subtitle">Tab Group</label>
            <input id="block_{{ $index }}_subtitle" name="{{ $prefix }}[subtitle]" class="wb-input" type="text" value="{{ old("{$prefix}.subtitle", $block->subtitle) }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_content">Tab Content</label>
        <textarea id="block_{{ $index }}_content" name="{{ $prefix }}[content]" class="wb-textarea" rows="8">{{ old("{$prefix}.content", $block->content) }}</textarea>
    </div>
</div>
