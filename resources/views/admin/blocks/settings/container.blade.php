<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="width">Width</label>
        <select id="width" name="width" class="wb-select">
            <option value="" @selected(old('width', $block->appearanceSetting('width')) === null)>Default</option>
            <option value="sm" @selected(old('width', $block->appearanceSetting('width')) === 'sm')>Small</option>
            <option value="md" @selected(old('width', $block->appearanceSetting('width')) === 'md')>Medium</option>
            <option value="lg" @selected(old('width', $block->appearanceSetting('width')) === 'lg')>Large</option>
            <option value="xl" @selected(old('width', $block->appearanceSetting('width')) === 'xl')>Extra Large</option>
            <option value="full" @selected(old('width', $block->appearanceSetting('width')) === 'full')>Full</option>
        </select>
        <div class="wb-text-sm wb-text-muted">Maps to shipped `wb-container-*` width classes only.</div>
    </div>
</div>
