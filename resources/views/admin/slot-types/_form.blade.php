@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('slot_types.'.$key, $adminLocale, $replace);
@endphp

<div class="wb-alert wb-alert-info">
    <div>
        <div class="wb-alert-title">{{ $adminText('read_only') }}</div>
        <div>{{ $adminText('catalog_short_help') }}</div>
    </div>
</div>
