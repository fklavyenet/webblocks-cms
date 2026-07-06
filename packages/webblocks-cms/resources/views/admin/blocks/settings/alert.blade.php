@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.presentation_settings.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="alert_variant">{{ $adminText('variant_label') }}</label>
        <select id="alert_variant" name="alert_variant" class="wb-select">
            <option value="info" @selected(old('alert_variant', $block->alertVariant()) === 'info')>{{ $adminText('info') }}</option>
            <option value="success" @selected(old('alert_variant', $block->alertVariant()) === 'success')>{{ $adminText('success') }}</option>
            <option value="warning" @selected(old('alert_variant', $block->alertVariant()) === 'warning')>{{ $adminText('warning') }}</option>
            <option value="danger" @selected(old('alert_variant', $block->alertVariant()) === 'danger')>{{ $adminText('danger') }}</option>
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('alert_variant_help') }}</div>
    </div>
</div>
