@php
    $selectedGalleryAssets = collect(old('gallery_media_ids', old('gallery_asset_ids', [])))
        ->map(fn ($id) => $id ? \WebBlocks\Cms\Models\Media::query()->find($id) : null)
        ->filter()
        ->values()
        ->whenEmpty(fn () => $selectedGalleryAssets ?? $block->galleryAssets());
@endphp

@include('webblocks-cms::admin.blocks.types.partials.gallery-items-editor', [
    'block' => $block,
    'selectedGalleryAssets' => $selectedGalleryAssets,
    'activeLocale' => $activeLocale ?? null,
    'isDefaultLocale' => $isDefaultLocale ?? true,
    'galleryItemsOldKey' => 'gallery_items',
    'modalIdPrefix' => 'gallery-item-editor',
])
