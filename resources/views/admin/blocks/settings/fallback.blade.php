@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.presentation_settings.'.$key, $adminLocale);
@endphp

<div class="wb-text-sm wb-text-muted">
    {{ $adminText('no_public_settings') }}
</div>
