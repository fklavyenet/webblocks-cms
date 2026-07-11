@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.presentation_settings.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="alignment">{{ $adminText('alignment_label') }}</label>
        <select id="alignment" name="alignment" class="wb-select">
            <option value="" @selected(old('alignment', $block->appearanceSetting('alignment')) === null)>{{ $adminText('default') }}</option>
            <option value="left" @selected(old('alignment', $block->appearanceSetting('alignment')) === 'left')>{{ $adminText('left') }}</option>
            <option value="center" @selected(old('alignment', $block->appearanceSetting('alignment')) === 'center')>{{ $adminText('center') }}</option>
            <option value="right" @selected(old('alignment', $block->appearanceSetting('alignment')) === 'right')>{{ $adminText('right') }}</option>
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('alignment_help') }}</div>
    </div>
</div>
