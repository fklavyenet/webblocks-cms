@php
    $mobileMediaLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $mobileMediaTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $mobileMediaText = fn (string $key) => $mobileMediaTranslator->get('admin.blocks.mobile_media.'.$key, $mobileMediaLocale);
@endphp

@include('webblocks-cms::admin.media.asset-picker-panel', [
    'name' => 'mobile-image-asset',
    'inputId' => 'mobile_media_id',
    'fieldName' => 'mobile_media_id',
    'selectedAsset' => $selectedMobileAsset ?? null,
    'buttonLabel' => $mobileMediaText('choose'),
    'replaceLabel' => $mobileMediaText('replace'),
    'clearLabel' => $mobileMediaText('remove'),
    'accept' => 'image',
    'panelMode' => 'overlay',
    'panelTitle' => $mobileMediaText('choose'),
    'compactControls' => true,
    'resultsVariant' => 'compact-list',
    'showUpload' => false,
    'selectorCard' => true,
    'selectorCardTitle' => $mobileMediaText('title'),
    'selectorHelperText' => $mobileMediaText('help'),
])
