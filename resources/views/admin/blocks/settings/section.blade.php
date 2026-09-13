@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.section_settings.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="spacing">{{ $adminText('spacing_label') }}</label>
        <select id="spacing" name="spacing" class="wb-select">
            <option value="" @selected(old('spacing', $block->appearanceSetting('spacing')) === null)>{{ $adminText('default') }}</option>
            <option value="sm" @selected(old('spacing', $block->appearanceSetting('spacing')) === 'sm')>{{ $adminText('compact') }}</option>
            <option value="lg" @selected(old('spacing', $block->appearanceSetting('spacing')) === 'lg')>{{ $adminText('spacious') }}</option>
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('spacing_help') }}</div>
    </div>
    <div class="wb-stack wb-gap-1">
        <label for="section_flow">{{ $adminText('flow_label') }}</label>
        <select id="section_flow" name="section_flow" class="wb-select">
            <option value="normal" @selected(old('section_flow', $block->appearanceSetting('flow') ?? 'normal') === 'normal')>{{ $adminText('flow_normal') }}</option>
            <option value="offset-up" @selected(old('section_flow', $block->appearanceSetting('flow')) === 'offset-up')>{{ $adminText('flow_offset_up') }}</option>
            <option value="overlap-previous" @selected(old('section_flow', $block->appearanceSetting('flow')) === 'overlap-previous')>{{ $adminText('flow_overlap_previous') }}</option>
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('flow_help') }}</div>
    </div>
</div>
