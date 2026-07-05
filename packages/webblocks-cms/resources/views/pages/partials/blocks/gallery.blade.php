@php
    $settings = is_array($block->settings)
        ? $block->settings
        : (json_decode((string) $block->settings, true) ?: []);
    $viewerId = 'wb-gallery-viewer-'.$block->id;
    $viewerTitle = $block->galleryViewerTitle();
    $captionMode = $block->galleryCaptionsMode();
    $overlayMode = $block->galleryOverlayMode();
    $lightboxEnabled = $block->galleryLightboxEnabled();
    $ariaLabel = $block->stringValueOrNull($block->translatedTextFieldValue('title')) ?: 'Gallery';
    $legacyAssetIds = collect($settings['asset_ids'] ?? $settings['gallery_asset_ids'] ?? [])
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => $id > 0)
        ->values();
    $legacyAssets = $legacyAssetIds->isEmpty()
        ? collect()
        : \WebBlocks\Cms\Models\Media::query()
            ->whereIn('id', $legacyAssetIds)
            ->get()
            ->sortBy(fn ($asset) => $legacyAssetIds->search($asset->id))
            ->values();
    $galleryRelations = $block->galleryItems()->loadMissing(['media', 'galleryItemTranslations']);
    $assetSource = $block->galleryAssets()->isNotEmpty() ? $block->galleryAssets()->values() : $legacyAssets;
    $legacyItems = collect($settings['items'] ?? $settings['images'] ?? []);
    $galleryText = static function ($value): string {
        $text = trim((string) $value);

        if ($text === '') {
            return '';
        }

        return preg_match('/^Imported from\b.+\bduring\b.+\bmigration\.?$/i', $text) ? '' : $text;
    };
    $firstGalleryText = static function (...$values) use ($galleryText): string {
        foreach ($values as $value) {
            $text = $galleryText($value);

            if ($text !== '') {
                return $text;
            }
        }

        return '';
    };
    $galleryItems = $assetSource
        ->map(function ($asset, $index) use ($legacyItems, $block, $galleryRelations, $firstGalleryText) {
            $legacyItem = $legacyItems->get($index);
            $galleryRelation = $galleryRelations->firstWhere('media_id', $asset->id);
            $translation = $block->resolvedGalleryItemTranslation($galleryRelation);
            $assetUrl = $asset->url();
            $fallbackUrl = is_array($legacyItem) ? ($legacyItem['media_url'] ?? $legacyItem['url'] ?? null) : null;
            $thumbnailUrl = $assetUrl ?: $fallbackUrl;
            $fullUrl = $assetUrl ?: $fallbackUrl;

            if (! $thumbnailUrl || ! $fullUrl) {
                return null;
            }

            $caption = $firstGalleryText($translation?->caption, $asset->caption, is_array($legacyItem) ? ($legacyItem['caption'] ?? $legacyItem['title'] ?? '') : '');
            $overlayTitle = $firstGalleryText($translation?->overlay_title);
            $overlayText = $firstGalleryText($translation?->overlay_text, $asset->description, is_array($legacyItem) ? ($legacyItem['meta'] ?? $legacyItem['subtitle'] ?? '') : '');
            $meta = $overlayTitle !== '' ? $overlayTitle : $overlayText;
            $alt = $firstGalleryText($translation?->alt_text, $asset->alt_text, is_array($legacyItem) ? ($legacyItem['alt'] ?? $legacyItem['title'] ?? '') : '', $caption, $asset->title, $asset->filename, 'Gallery image');

            return [
                'thumbnail_url' => $thumbnailUrl,
                'full_url' => $fullUrl,
                'alt' => $alt,
                'caption' => $caption,
                'meta' => $meta,
                'overlay_title' => $overlayTitle,
                'overlay_text' => $overlayText,
                'width' => $asset->width,
                'height' => $asset->height,
            ];
        })
        ->when($assetSource->isEmpty(), function ($items) use ($legacyItems, $block, $firstGalleryText) {
            return $items->merge(
                $legacyItems->map(function ($legacyItem) use ($block, $firstGalleryText) {
                    if (! is_array($legacyItem)) {
                        return null;
                    }

                    $mediaUrl = $legacyItem['media_url'] ?? $legacyItem['url'] ?? null;

                    if (! $mediaUrl) {
                        return null;
                    }

                    $caption = $firstGalleryText($legacyItem['caption'] ?? null, $legacyItem['title'] ?? null);
                    $overlayText = $firstGalleryText($legacyItem['meta'] ?? null, $legacyItem['subtitle'] ?? null);
                    $alt = $firstGalleryText($legacyItem['alt'] ?? null, $legacyItem['title'] ?? null, $caption, 'Gallery image');

                    return [
                        'thumbnail_url' => $mediaUrl,
                        'full_url' => $mediaUrl,
                        'alt' => $alt,
                        'caption' => $caption,
                        'meta' => $overlayText,
                        'overlay_title' => '',
                        'overlay_text' => $overlayText,
                        'width' => null,
                        'height' => null,
                    ];
                })
            );
        })
        ->filter()
        ->values();
@endphp

<div
    class="wb-gallery wb-gallery--{{ $block->galleryVariant() }} wb-gallery--cols-{{ $block->galleryColumns() }} wb-gallery--gap-{{ $block->galleryGap() }} wb-gallery--aspect-{{ str_replace(':', '-', $block->galleryAspectRatio()) }}"
    data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}"
    data-wb-gallery-variant="{{ $block->galleryVariant() }}"
    data-wb-gallery-captions="{{ $captionMode }}"
    aria-label="{{ $ariaLabel }}"
>
    @if ($galleryItems->isNotEmpty())
        <div class="wb-gallery-grid">
            @foreach ($galleryItems as $item)
                @php
                    $showBelowCaption = $captionMode === 'below' && $item['caption'] !== '';
                    $showOverlay = in_array($captionMode, ['overlay', 'on-hover'], true) && ($item['caption'] !== '' || $item['overlay_title'] !== '' || $item['overlay_text'] !== '');
                    $triggerClass = $lightboxEnabled ? 'wb-gallery-trigger' : 'wb-gallery-link';
                @endphp
                <figure class="wb-gallery-item">
                    <a
                        href="{{ $item['full_url'] }}"
                        class="{{ $triggerClass }}"
                        @if ($lightboxEnabled)
                            data-wb-gallery-target="#{{ $viewerId }}"
                            data-wb-gallery-full="{{ $item['full_url'] }}"
                            data-wb-gallery-alt="{{ $item['alt'] }}"
                            @if ($item['caption'] !== '') data-wb-gallery-caption="{{ $item['caption'] }}" @endif
                            @if (($item['meta'] ?? '') !== '') data-wb-gallery-meta="{{ $item['meta'] }}" @endif
                            @if ($item['width']) data-wb-gallery-width="{{ $item['width'] }}" @endif
                            @if ($item['height']) data-wb-gallery-height="{{ $item['height'] }}" @endif
                        @endif
                    >
                        <img
                            src="{{ $item['thumbnail_url'] }}"
                            alt="{{ $item['alt'] }}"
                            class="wb-gallery-media"
                            @if ($item['width']) width="{{ $item['width'] }}" @endif
                            @if ($item['height']) height="{{ $item['height'] }}" @endif
                        >

                        @if ($showOverlay)
                            <span class="wb-gallery-caption wb-gallery-caption--{{ $overlayMode }} @if($captionMode === 'on-hover') wb-gallery-caption--hover @endif">
                                @if ($item['overlay_title'] !== '')
                                    <span class="wb-gallery-caption-title">{{ $item['overlay_title'] }}</span>
                                @endif
                                @if ($item['overlay_text'] !== '')
                                    <span class="wb-gallery-caption-meta">{{ $item['overlay_text'] }}</span>
                                @elseif ($item['caption'] !== '')
                                    <span class="wb-gallery-caption-meta">{{ $item['caption'] }}</span>
                                @endif
                            </span>
                        @endif
                    </a>

                    @if ($showBelowCaption)
                        <figcaption class="wb-gallery-caption">{{ $item['caption'] }}</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>

        @if ($lightboxEnabled)
            @php
                $galleryViewerHtml = view('webblocks-cms::pages.partials.blocks.gallery-viewer', [
                    'viewerId' => $viewerId,
                    'viewerTitle' => $viewerTitle,
                    'galleryItems' => $galleryItems,
                ])->render();
                app(\WebBlocks\Cms\Support\Blocks\PublicOverlayRegistry::class)->push($galleryViewerHtml);
            @endphp
        @endif
    @endif
</div>
