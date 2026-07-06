@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.cluster_settings.'.$key, $adminLocale);
    $clusterGap = old('cluster_gap', $block->clusterGap());
    $clusterJustify = old('cluster_justify', $block->clusterJustify());
    $clusterAlign = old('cluster_align', $block->clusterAlign());
    $clusterWrap = old('cluster_wrap', $block->clusterWrap());
    $clusterWidth = old('cluster_width', $block->clusterWidth());
@endphp

<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="cluster_width">{{ $adminText('width_label') }}</label>
        <select id="cluster_width" name="cluster_width" class="wb-select">
            <option value="" @selected($clusterWidth === 'auto')>{{ $adminText('auto') }}</option>
            <option value="full" @selected($clusterWidth === 'full')>{{ $adminText('full') }}</option>
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('width_help') }}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="cluster_justify">{{ $adminText('justify_label') }}</label>
        <select id="cluster_justify" name="cluster_justify" class="wb-select">
            <option value="" @selected($clusterJustify === 'start')>{{ $adminText('start') }}</option>
            <option value="center" @selected($clusterJustify === 'center')>{{ $adminText('center') }}</option>
            <option value="end" @selected($clusterJustify === 'end')>{{ $adminText('end') }}</option>
            <option value="between" @selected($clusterJustify === 'between')>{{ $adminText('between') }}</option>
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('justify_help') }}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="cluster_align">{{ $adminText('align_label') }}</label>
        <select id="cluster_align" name="cluster_align" class="wb-select">
            <option value="" @selected($clusterAlign === 'center')>{{ $adminText('center') }}</option>
            <option value="start" @selected($clusterAlign === 'start')>{{ $adminText('start') }}</option>
            <option value="end" @selected($clusterAlign === 'end')>{{ $adminText('end') }}</option>
            <option value="stretch" @selected($clusterAlign === 'stretch')>{{ $adminText('stretch') }}</option>
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('align_help') }}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="cluster_wrap">{{ $adminText('wrap_label') }}</label>
        <select id="cluster_wrap" name="cluster_wrap" class="wb-select">
            <option value="" @selected($clusterWrap === 'wrap')>{{ $adminText('wrap') }}</option>
            <option value="nowrap" @selected($clusterWrap === 'nowrap')>{{ $adminText('nowrap') }}</option>
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('wrap_help') }}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="cluster_gap">{{ $adminText('gap_label') }}</label>
        <select id="cluster_gap" name="cluster_gap" class="wb-select">
            <option value="" @selected($clusterGap === 'default')>{{ $adminText('default') }}</option>
            <option value="none" @selected($clusterGap === 'none')>{{ $adminText('none') }}</option>
            <option value="xs" @selected($clusterGap === 'xs')>XS</option>
            <option value="sm" @selected($clusterGap === 'sm')>SM</option>
            <option value="md" @selected($clusterGap === 'md')>MD</option>
            <option value="lg" @selected($clusterGap === 'lg')>LG</option>
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('gap_help') }}</div>
    </div>
</div>
