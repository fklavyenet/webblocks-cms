<div class="wb-stack wb-gap-1">
    <label for="block_{{ $index }}_content">Rich Text Content</label>
    <textarea id="block_{{ $index }}_content" name="{{ $prefix }}[content]" class="wb-textarea" rows="10" required>{{ old("{$prefix}.content", $block->content) }}</textarea>
    <span>A plain textarea is currently used here while preserving the rich text intent.</span>
</div>
