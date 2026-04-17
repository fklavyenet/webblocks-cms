@php
    $submittedGalleryIds = old("{$prefix}.gallery_asset_ids", $block->galleryAssetIds());
@endphp

<div class="wb-grid wb-grid-2">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_title">Gallery Title</label>
        <input id="block_{{ $index }}_title" name="{{ $prefix }}[title]" class="wb-input" type="text" value="{{ old("{$prefix}.title", $block->title) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_subtitle">Description</label>
        <input id="block_{{ $index }}_subtitle" name="{{ $prefix }}[subtitle]" class="wb-input" type="text" value="{{ old("{$prefix}.subtitle", $block->subtitle) }}">
    </div>
</div>

<div class="wb-grid wb-grid-3">
    @for ($galleryIndex = 0; $galleryIndex < 3; $galleryIndex++)
        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_gallery_asset_{{ $galleryIndex }}">Gallery Asset {{ $galleryIndex + 1 }}</label>
            <input id="block_{{ $index }}_gallery_asset_{{ $galleryIndex }}" name="{{ $prefix }}[gallery_asset_ids][]" class="wb-input" type="number" min="1" value="{{ $submittedGalleryIds[$galleryIndex] ?? '' }}">
        </div>
    @endfor
</div>
