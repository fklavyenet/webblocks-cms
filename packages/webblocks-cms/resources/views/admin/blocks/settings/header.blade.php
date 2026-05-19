<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="alignment">Alignment</label>
        <select id="alignment" name="alignment" class="wb-select">
            <option value="" @selected(old('alignment', $block->appearanceSetting('alignment')) === null)>Default</option>
            <option value="left" @selected(old('alignment', $block->appearanceSetting('alignment')) === 'left')>Left</option>
            <option value="center" @selected(old('alignment', $block->appearanceSetting('alignment')) === 'center')>Center</option>
            <option value="right" @selected(old('alignment', $block->appearanceSetting('alignment')) === 'right')>Right</option>
        </select>
        <div class="wb-text-sm wb-text-muted">Applies shipped WebBlocks UI text alignment classes only.</div>
    </div>
</div>
