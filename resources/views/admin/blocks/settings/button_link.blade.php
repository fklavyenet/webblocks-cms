<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="variant">Variant</label>
        <select id="variant" name="variant" class="wb-select">
            <option value="primary" @selected(old('variant', $block->variant ?: 'primary') === 'primary')>Primary</option>
            <option value="secondary" @selected(old('variant', $block->variant ?: 'primary') === 'secondary')>Secondary</option>
        </select>
        <div class="wb-text-sm wb-text-muted">Applies shipped WebBlocks UI button classes only.</div>
    </div>
</div>
