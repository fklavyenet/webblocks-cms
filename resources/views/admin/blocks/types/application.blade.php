@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.application.'.$key, $adminLocale);
@endphp

@include('webblocks-cms::admin.blocks.types.application-fields', [
    'namePrefix' => '',
    'oldPrefix' => '',
    'idPrefix' => '',
])
