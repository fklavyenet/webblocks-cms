<div class="wb-stack wb-gap-4">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Section Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="variant">Section Variant</label>
            <select id="variant" name="variant" class="wb-select">
                @foreach (['default', 'muted', 'accent', 'wide'] as $variant)
                    <option value="{{ $variant }}" @selected(old('variant', $block->variant ?: 'default') === $variant)>{{ $variant }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Section Intro</label>
        <textarea id="content" name="content" class="wb-textarea" rows="6">{{ old('content', $block->content) }}</textarea>
    </div>
</div>
