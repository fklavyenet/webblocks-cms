<div class="wb-stack wb-gap-1">
    <label for="content">Rich Text Content</label>
    <textarea id="content" name="content" class="wb-textarea" rows="10" required>{{ old('content', $block->content) }}</textarea>
    <span>A plain textarea is currently used here while preserving the rich text intent.</span>
</div>
