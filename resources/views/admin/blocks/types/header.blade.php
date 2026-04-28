<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Header text is translated per locale. The heading level stays shared across locales.</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label for="text">Text</label>
        <textarea id="text" name="text" class="wb-textarea" rows="3" required>{{ old('text', $block->title) }}</textarea>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="level">Level</label>
        <select id="level" name="level" class="wb-select" required>
            @foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $level)
                <option value="{{ $level }}" @selected(old('level', $block->variant ?: 'h2') === $level)>{{ strtoupper($level) }}</option>
            @endforeach
        </select>
    </div>
</div>
