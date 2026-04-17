@php
    $selectedGalleryAssets = collect(old('gallery_asset_ids', []))
        ->map(fn ($id) => $id ? \App\Models\Asset::query()->find($id) : null)
        ->filter()
        ->values()
        ->whenEmpty(fn () => $selectedGalleryAssets ?? $block->galleryAssets());
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Gallery Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="subtitle">Description</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label>Gallery Assets</label>
        @include('admin.media.asset-picker-panel', [
            'name' => 'gallery-assets',
            'mode' => 'multiple',
            'inputId' => 'gallery_asset_ids',
            'fieldName' => 'gallery_asset_ids',
            'selectedAssets' => $selectedGalleryAssets,
            'buttonLabel' => 'Choose from Media',
            'replaceLabel' => 'Add More Assets',
            'clearLabel' => 'Remove All',
            'accept' => 'image',
        ])
        <span>Choose internal image assets and save their order through block asset relations.</span>
    </div>
</div>
