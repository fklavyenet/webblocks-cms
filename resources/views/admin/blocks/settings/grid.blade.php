@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.grid_settings.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="grid_columns">{{ $adminText('columns_label') }}</label>
        <select id="grid_columns" name="grid_columns" class="wb-select">
            <option value="2" @selected(old('grid_columns', $block->appearanceSetting('columns') ?? '3') === '2')>{{ $adminText('two_columns') }}</option>
            <option value="3" @selected(old('grid_columns', $block->appearanceSetting('columns') ?? '3') === '3')>{{ $adminText('three_columns') }}</option>
            <option value="4" @selected(old('grid_columns', $block->appearanceSetting('columns') ?? '3') === '4')>{{ $adminText('four_columns') }}</option>
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('columns_help') }}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="grid_gap">{{ $adminText('gap_label') }}</label>
        <select id="grid_gap" name="grid_gap" class="wb-select">
            <option value="" @selected(old('grid_gap', $block->appearanceSetting('gap')) === null)>{{ $adminText('default') }}</option>
            <option value="3" @selected(old('grid_gap', $block->appearanceSetting('gap')) === '3')>{{ $adminText('compact') }}</option>
            <option value="4" @selected(old('grid_gap', $block->appearanceSetting('gap')) === '4')>{{ $adminText('regular') }}</option>
            <option value="6" @selected(old('grid_gap', $block->appearanceSetting('gap')) === '6')>{{ $adminText('spacious') }}</option>
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('gap_help') }}</div>
    </div>

    <label class="wb-cluster wb-cluster-2 wb-items-center" for="grid_alternate_media_text_sections">
        <input id="grid_alternate_media_text_sections" name="grid_alternate_media_text_sections" type="hidden" value="0">
        <input id="grid_alternate_media_text_sections" name="grid_alternate_media_text_sections" type="checkbox" value="1" @checked((bool) old('grid_alternate_media_text_sections', $block->gridAlternatesMediaTextSections()))>
        <span>{{ $adminText('alternate_label') }}</span>
    </label>

    <div class="wb-stack wb-gap-1">
        <label for="grid_alternate_start">{{ $adminText('first_layout_label') }}</label>
        <select id="grid_alternate_start" name="grid_alternate_start" class="wb-select">
            <option value="media_left" @selected(old('grid_alternate_start', $block->gridAlternateStart()) === 'media_left')>{{ $adminText('media_left') }}</option>
            <option value="text_left" @selected(old('grid_alternate_start', $block->gridAlternateStart()) === 'text_left')>{{ $adminText('text_left') }}</option>
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('alternate_help') }}</div>
    </div>
</div>
