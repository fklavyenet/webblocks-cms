<div class="wb-stack wb-gap-4">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Callout Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="variant">Tone</label>
            <select id="variant" name="variant" class="wb-select">
                @foreach (['info', 'success', 'warning', 'danger'] as $tone)
                    <option value="{{ $tone }}" @selected(old('variant', $block->variant ?: 'info') === $tone)>{{ $tone }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Callout Content</label>
        <textarea id="content" name="content" class="wb-textarea" rows="6">{{ old('content', $block->content) }}</textarea>
    </div>
</div>
