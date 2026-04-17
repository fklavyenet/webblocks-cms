<div class="wb-stack wb-gap-4">
    <div class="wb-stack wb-gap-1">
        <label for="content">Quote Text</label>
        <textarea id="content" name="content" class="wb-textarea" rows="6" required>{{ old('content', $block->content) }}</textarea>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Author</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="subtitle">Source</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
        </div>
    </div>
</div>
