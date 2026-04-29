<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="card_url">URL</label>
        <input id="card_url" name="card_url" class="wb-input" type="text" value="{{ old('card_url', $block->cardUrl()) }}" placeholder="/getting-started">
        <div class="wb-text-sm wb-text-muted">Shared card destination. Use a full URL, site path, anchor, mailto link, or telephone link.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="card_target">Target</label>
        <select id="card_target" name="card_target" class="wb-select">
            <option value="_self" @selected(old('card_target', $block->cardTarget()) === '_self')>Same tab</option>
            <option value="_blank" @selected(old('card_target', $block->cardTarget()) === '_blank')>New tab</option>
        </select>
    </div>
</div>
