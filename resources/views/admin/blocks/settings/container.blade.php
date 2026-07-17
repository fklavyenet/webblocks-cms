@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.container_settings.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="width">{{ $adminText('width_label') }}</label>
        <select id="width" name="width" class="wb-select">
            <option value="" @selected(old('width', $block->appearanceSetting('width')) === null)>{{ $adminText('default') }}</option>
            <option value="sm" @selected(old('width', $block->appearanceSetting('width')) === 'sm')>{{ $adminText('small') }}</option>
            <option value="md" @selected(old('width', $block->appearanceSetting('width')) === 'md')>{{ $adminText('medium') }}</option>
            <option value="lg" @selected(old('width', $block->appearanceSetting('width')) === 'lg')>{{ $adminText('large') }}</option>
            <option value="xl" @selected(old('width', $block->appearanceSetting('width')) === 'xl')>{{ $adminText('extra_large') }}</option>
            <option value="full" @selected(old('width', $block->appearanceSetting('width')) === 'full')>{{ $adminText('full') }}</option>
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('width_help') }}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="container_flow">{{ $adminText('flow_label') }}</label>
        <select id="container_flow" name="container_flow" class="wb-select">
            <option value="" @selected(old('container_flow', $block->setting('flow')) === null)>{{ $adminText('default_none') }}</option>
            <option value="none" @selected(old('container_flow', $block->setting('flow')) === 'none')>{{ $adminText('none') }}</option>
            <option value="stack" @selected(old('container_flow', $block->setting('flow')) === 'stack')>{{ $adminText('stack') }}</option>
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('flow_help') }}</div>
    </div>
</div>
