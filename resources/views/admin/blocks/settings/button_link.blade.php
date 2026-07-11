@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.button_link_settings.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="variant">{{ $adminText('variant_label') }}</label>
        <select id="variant" name="variant" class="wb-select">
            <option value="primary" @selected(old('variant', $block->variant ?: 'primary') === 'primary')>{{ $adminText('primary') }}</option>
            <option value="secondary" @selected(old('variant', $block->variant ?: 'primary') === 'secondary')>{{ $adminText('secondary') }}</option>
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('variant_help') }}</div>
    </div>
</div>
