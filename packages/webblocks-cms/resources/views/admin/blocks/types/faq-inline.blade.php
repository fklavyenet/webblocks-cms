<div class="wb-stack wb-gap-4">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_title">Question</label>
        <input id="block_{{ $index }}_title" name="{{ $prefix }}[title]" class="wb-input" type="text" value="{{ old("{$prefix}.title", $block->title) }}" required>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_content">Answer</label>
        <textarea id="block_{{ $index }}_content" name="{{ $prefix }}[content]" class="wb-textarea" rows="6" required>{{ old("{$prefix}.content", $block->content) }}</textarea>
    </div>
</div>
