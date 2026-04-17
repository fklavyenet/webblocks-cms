<div class="wb-stack wb-gap-1">
    <label for="block_{{ $index }}_content">Text Content</label>
    <textarea id="block_{{ $index }}_content" name="{{ $prefix }}[content]" class="wb-textarea" rows="8" required>{{ old("{$prefix}.content", $block->content) }}</textarea>
</div>
