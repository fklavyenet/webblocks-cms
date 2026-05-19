<div class="wb-stack wb-gap-4">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_content">Quote Text</label>
        <textarea id="block_{{ $index }}_content" name="{{ $prefix }}[content]" class="wb-textarea" rows="6" required>{{ old("{$prefix}.content", $block->content) }}</textarea>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_title">Author</label>
            <input id="block_{{ $index }}_title" name="{{ $prefix }}[title]" class="wb-input" type="text" value="{{ old("{$prefix}.title", $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_subtitle">Source</label>
            <input id="block_{{ $index }}_subtitle" name="{{ $prefix }}[subtitle]" class="wb-input" type="text" value="{{ old("{$prefix}.subtitle", $block->subtitle) }}">
        </div>
    </div>
</div>
