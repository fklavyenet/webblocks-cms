<div class="wb-stack wb-gap-1">
    <label for="block_{{ $index }}_content">Raw HTML</label>
    <textarea id="block_{{ $index }}_content" name="{{ $prefix }}[content]" class="wb-textarea" rows="10" required>{{ old("{$prefix}.content", $block->content) }}</textarea>
</div>
