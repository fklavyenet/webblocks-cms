<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="cluster_gap">Gap</label>
        <select id="cluster_gap" name="cluster_gap" class="wb-select">
            <option value="" @selected(old('cluster_gap', $block->appearanceSetting('gap')) === null)>Default</option>
            <option value="2" @selected(old('cluster_gap', $block->appearanceSetting('gap')) === '2')>Compact</option>
            <option value="4" @selected(old('cluster_gap', $block->appearanceSetting('gap')) === '4')>Regular</option>
            <option value="6" @selected(old('cluster_gap', $block->appearanceSetting('gap')) === '6')>Spacious</option>
        </select>
        <div class="wb-text-sm wb-text-muted">Maps only to shipped `wb-cluster-2`, `wb-cluster-4`, and `wb-cluster-6` classes.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="cluster_alignment">Alignment</label>
        <select id="cluster_alignment" name="cluster_alignment" class="wb-select">
            <option value="" @selected(old('cluster_alignment', $block->appearanceSetting('alignment')) === null || old('cluster_alignment', $block->appearanceSetting('alignment')) === 'start')>Default</option>
            <option value="center" @selected(old('cluster_alignment', $block->appearanceSetting('alignment')) === 'center')>Center</option>
            <option value="end" @selected(old('cluster_alignment', $block->appearanceSetting('alignment')) === 'end')>End</option>
        </select>
        <div class="wb-text-sm wb-text-muted">Maps only to shipped `wb-cluster-center` and `wb-cluster-end` classes. Start uses the default `wb-cluster` flow.</div>
    </div>
</div>
