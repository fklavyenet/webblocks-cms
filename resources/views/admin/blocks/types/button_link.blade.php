<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Button label is translated per locale. URL, target, and variant stay shared across locales.</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label for="label">Label</label>
        <input id="label" name="label" class="wb-input" type="text" value="{{ old('label', $block->title) }}" required>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="url">URL</label>
        <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $block->buttonLinkUrl()) }}" placeholder="/start-here" required>
        <div class="wb-text-sm wb-text-muted">Use a full URL, site path, anchor, mailto link, or telephone link.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="target">Target</label>
        <select id="target" name="target" class="wb-select">
            <option value="_self" @selected(old('target', $block->buttonLinkTarget()) === '_self')>Same tab</option>
            <option value="_blank" @selected(old('target', $block->buttonLinkTarget()) === '_blank')>New tab</option>
        </select>
    </div>
</div>
