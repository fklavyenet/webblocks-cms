<div class="wb-stack wb-gap-4">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Columns Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="subtitle">Columns Subtitle</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Intro Text</label>
        <textarea id="content" name="content" class="wb-textarea" rows="5">{{ old('content', $block->content) }}</textarea>
    </div>

    @include('admin.blocks.partials.column-items-editor', [
        'block' => $block,
        'columnItemBlockType' => $columnItemBlockType ?? null,
    ])
</div>
