<div class="wb-stack wb-gap-4">
    <div class="wb-stack wb-gap-1">
        <label for="title">Question</label>
        <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Answer</label>
        <textarea id="content" name="content" class="wb-textarea" rows="6" required>{{ old('content', $block->content) }}</textarea>
    </div>
</div>
