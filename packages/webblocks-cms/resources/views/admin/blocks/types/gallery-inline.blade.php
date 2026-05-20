@include('webblocks-cms::admin.blocks.types.partials.gallery-items-editor', [
    'block' => $block,
    'selectedGalleryAssets' => collect(old("{$prefix}.gallery_media_ids", old("{$prefix}.gallery_asset_ids", $block->galleryMediaIds())))
        ->map(fn ($id) => $id ? \WebBlocks\Cms\Models\Media::query()->find($id) : null)
        ->filter()
        ->values()
        ->whenEmpty(fn () => $block->galleryAssets()),
    'galleryItemsOldKey' => "{$prefix}.gallery_items",
    'inputPrefix' => $prefix,
    'modalIdPrefix' => 'inline-gallery-item-editor-'.$index,
])
