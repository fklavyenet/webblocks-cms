<div class="wb-grid wb-grid-2">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_title">Section Title</label>
        <input id="block_{{ $index }}_title" name="{{ $prefix }}[title]" class="wb-input" type="text" value="{{ old("{$prefix}.title", $block->title) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_variant">Section Variant</label>
        <select id="block_{{ $index }}_variant" name="{{ $prefix }}[variant]" class="wb-select">
            @foreach (['default', 'muted', 'accent', 'wide'] as $variant)
                <option value="{{ $variant }}" @selected(old("{$prefix}.variant", $block->variant ?: 'default') === $variant)>{{ $variant }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="wb-stack wb-gap-1">
    <label for="block_{{ $index }}_content">Section Intro</label>
    <textarea id="block_{{ $index }}_content" name="{{ $prefix }}[content]" class="wb-textarea" rows="6">{{ old("{$prefix}.content", $block->content) }}</textarea>
</div>
