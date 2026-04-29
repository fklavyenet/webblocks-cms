<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="spacing">Spacing</label>
        <select id="spacing" name="spacing" class="wb-select">
            <option value="" @selected(old('spacing', $block->appearanceSetting('spacing')) === null)>Default</option>
            <option value="sm" @selected(old('spacing', $block->appearanceSetting('spacing')) === 'sm')>Compact</option>
            <option value="lg" @selected(old('spacing', $block->appearanceSetting('spacing')) === 'lg')>Spacious</option>
        </select>
        <div class="wb-text-sm wb-text-muted">Maps to shipped `wb-section-sm` and `wb-section-lg` spacing classes.</div>
    </div>
</div>
