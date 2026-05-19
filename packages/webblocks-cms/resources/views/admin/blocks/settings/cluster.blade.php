@php
    $clusterGap = old('cluster_gap', $block->clusterGap());
    $clusterJustify = old('cluster_justify', $block->clusterJustify());
    $clusterAlign = old('cluster_align', $block->clusterAlign());
    $clusterWrap = old('cluster_wrap', $block->clusterWrap());
    $clusterWidth = old('cluster_width', $block->clusterWidth());
@endphp

<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="cluster_width">Width</label>
        <select id="cluster_width" name="cluster_width" class="wb-select">
            <option value="" @selected($clusterWidth === 'auto')>Auto</option>
            <option value="full" @selected($clusterWidth === 'full')>Full</option>
        </select>
        <div class="wb-text-sm wb-text-muted">Use `Full` when the cluster should fill its parent, such as a navbar row that needs left and right groups to separate.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="cluster_justify">Justify</label>
        <select id="cluster_justify" name="cluster_justify" class="wb-select">
            <option value="" @selected($clusterJustify === 'start')>Start</option>
            <option value="center" @selected($clusterJustify === 'center')>Center</option>
            <option value="end" @selected($clusterJustify === 'end')>End</option>
            <option value="between" @selected($clusterJustify === 'between')>Between</option>
        </select>
        <div class="wb-text-sm wb-text-muted">Controls horizontal distribution for grouped children using WebBlocks UI cluster modifiers.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="cluster_align">Align</label>
        <select id="cluster_align" name="cluster_align" class="wb-select">
            <option value="" @selected($clusterAlign === 'center')>Center</option>
            <option value="start" @selected($clusterAlign === 'start')>Start</option>
            <option value="end" @selected($clusterAlign === 'end')>End</option>
            <option value="stretch" @selected($clusterAlign === 'stretch')>Stretch</option>
        </select>
        <div class="wb-text-sm wb-text-muted">Controls cross-axis alignment. `Center` keeps the current cluster default.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="cluster_wrap">Wrap</label>
        <select id="cluster_wrap" name="cluster_wrap" class="wb-select">
            <option value="" @selected($clusterWrap === 'wrap')>Wrap</option>
            <option value="nowrap" @selected($clusterWrap === 'nowrap')>Nowrap</option>
        </select>
        <div class="wb-text-sm wb-text-muted">Use `Nowrap` for single-row groups such as navbar rows. `Wrap` preserves the current cluster behavior.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="cluster_gap">Gap</label>
        <select id="cluster_gap" name="cluster_gap" class="wb-select">
            <option value="" @selected($clusterGap === 'default')>Default</option>
            <option value="none" @selected($clusterGap === 'none')>None</option>
            <option value="xs" @selected($clusterGap === 'xs')>XS</option>
            <option value="sm" @selected($clusterGap === 'sm')>SM</option>
            <option value="md" @selected($clusterGap === 'md')>MD</option>
            <option value="lg" @selected($clusterGap === 'lg')>LG</option>
        </select>
        <div class="wb-text-sm wb-text-muted">Default keeps the shipped cluster gap. Other options map to WebBlocks UI utilities when available, with a small CMS fallback only for `None`.</div>
    </div>
</div>
