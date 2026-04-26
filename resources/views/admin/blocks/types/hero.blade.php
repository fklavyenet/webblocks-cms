<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Hero eyebrow, headline, and supporting copy are translated per locale. Variant stays shared across locales.</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="subtitle">Eyebrow / Label</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="variant">Variant</label>
            <select id="variant" name="variant" class="wb-select">
                @foreach ([
                    'default' => 'default',
                    'muted' => 'muted',
                    'accent' => 'accent',
                    'centered' => 'centered',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(old('variant', $block->variant ?: 'default') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="title">Headline</label>
        <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Supporting Copy</label>
        <textarea id="content" name="content" class="wb-textarea" rows="6">{{ old('content', $block->content) }}</textarea>
        <div class="wb-text-sm wb-text-muted">Use child Button blocks for actions. Do not paste button HTML into the copy field.</div>
    </div>
</div>
