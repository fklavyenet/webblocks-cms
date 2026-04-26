<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Columns intro copy is translated per locale. Child column item structure remains shared.</div>
        </div>
    @endif

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
        <label for="variant">Columns Variant</label>
        <select id="variant" name="variant" class="wb-select">
            @foreach ([
                'cards' => 'Cards',
                'plain' => 'Plain',
                'stats' => 'Stats',
                'links' => 'Links',
            ] as $value => $label)
                <option value="{{ $value }}" @selected(old('variant', $block->variant ?: 'cards') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="wb-text-sm wb-text-muted">The Columns variant controls how child Column Item blocks render publicly.</div>
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
