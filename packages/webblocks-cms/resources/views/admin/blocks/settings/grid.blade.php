<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="grid_columns">Columns</label>
        <select id="grid_columns" name="grid_columns" class="wb-select">
            <option value="2" @selected(old('grid_columns', $block->appearanceSetting('columns') ?? '3') === '2')>Two columns</option>
            <option value="3" @selected(old('grid_columns', $block->appearanceSetting('columns') ?? '3') === '3')>Three columns</option>
            <option value="4" @selected(old('grid_columns', $block->appearanceSetting('columns') ?? '3') === '4')>Four columns</option>
        </select>
        <div class="wb-text-sm wb-text-muted">Maps only to shipped `wb-grid-2`, `wb-grid-3`, and `wb-grid-4` classes.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="grid_gap">Gap</label>
        <select id="grid_gap" name="grid_gap" class="wb-select">
            <option value="" @selected(old('grid_gap', $block->appearanceSetting('gap')) === null)>Default</option>
            <option value="3" @selected(old('grid_gap', $block->appearanceSetting('gap')) === '3')>Compact</option>
            <option value="4" @selected(old('grid_gap', $block->appearanceSetting('gap')) === '4')>Regular</option>
            <option value="6" @selected(old('grid_gap', $block->appearanceSetting('gap')) === '6')>Spacious</option>
        </select>
        <div class="wb-text-sm wb-text-muted">Maps only to shipped `wb-gap-3`, `wb-gap-4`, and `wb-gap-6` classes.</div>
    </div>
</div>
