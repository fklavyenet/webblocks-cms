@php
    $settings = is_array($block->settings)
        ? $block->settings
        : (json_decode((string) $block->settings, true) ?: []);
    $legacyAssetIds = collect($settings['asset_ids'] ?? $settings['gallery_asset_ids'] ?? [])
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => $id > 0)
        ->values();
    $legacyAssets = $legacyAssetIds->isEmpty()
        ? collect()
        : \App\Models\Asset::query()
            ->whereIn('id', $legacyAssetIds)
            ->get()
            ->sortBy(fn ($asset) => $legacyAssetIds->search($asset->id))
            ->values();
    $assetSource = $block->galleryAssets()->isNotEmpty() ? $block->galleryAssets()->values() : $legacyAssets;
    $legacyItems = collect($settings['items'] ?? $settings['images'] ?? []);
    $galleryItems = $assetSource
        ->map(function ($asset, $index) use ($legacyItems, $block) {
            $legacyItem = $legacyItems->get($index);
            $assetUrl = $asset->url();
            $fallbackUrl = is_array($legacyItem) ? ($legacyItem['media_url'] ?? $legacyItem['url'] ?? null) : null;
            $thumbnailUrl = $assetUrl ?: $fallbackUrl;
            $fullUrl = $assetUrl ?: $fallbackUrl;

            if (! $thumbnailUrl || ! $fullUrl) {
                return null;
            }

            $caption = trim((string) ($asset->caption ?: (is_array($legacyItem) ? ($legacyItem['caption'] ?? $legacyItem['title'] ?? '') : '')));
            $meta = trim((string) ((is_array($legacyItem) ? ($legacyItem['meta'] ?? $legacyItem['subtitle'] ?? '') : '') ?: $asset->description ?: ''));
            $alt = trim((string) ($asset->alt_text ?: (is_array($legacyItem) ? ($legacyItem['alt'] ?? $legacyItem['title'] ?? '') : '') ?: $caption ?: $asset->title ?: $block->title ?: 'Gallery image'));

            return [
                'thumbnail_url' => $thumbnailUrl,
                'full_url' => $fullUrl,
                'alt' => $alt,
                'caption' => $caption,
                'meta' => $meta,
                'width' => $asset->width,
                'height' => $asset->height,
            ];
        })
        ->when($assetSource->isEmpty(), function ($items) use ($legacyItems, $block) {
            return $items->merge(
                $legacyItems->map(function ($legacyItem) use ($block) {
                    if (! is_array($legacyItem)) {
                        return null;
                    }

                    $mediaUrl = $legacyItem['media_url'] ?? $legacyItem['url'] ?? null;

                    if (! $mediaUrl) {
                        return null;
                    }

                    $caption = trim((string) ($legacyItem['caption'] ?? $legacyItem['title'] ?? ''));
                    $meta = trim((string) ($legacyItem['meta'] ?? $legacyItem['subtitle'] ?? ''));
                    $alt = trim((string) ($legacyItem['alt'] ?? $legacyItem['title'] ?? $caption ?: $block->title ?: 'Gallery image'));

                    return [
                        'thumbnail_url' => $mediaUrl,
                        'full_url' => $mediaUrl,
                        'alt' => $alt,
                        'caption' => $caption,
                        'meta' => $meta,
                        'width' => null,
                        'height' => null,
                    ];
                })
            );
        })
        ->filter()
        ->values();
@endphp

<div class="wb-stack wb-gap-3">
    @if ($block->title)
        <h3>{{ $block->title }}</h3>
    @endif

    @if ($block->subtitle)
        <p>{{ $block->subtitle }}</p>
    @endif

    @if ($galleryItems->isNotEmpty())
        <section class="wb-gallery" aria-label="{{ $block->title ?: 'Gallery' }}">
            <div class="wb-gallery-grid">
                @foreach ($galleryItems as $item)
                    <figure class="wb-gallery-item">
                        <a
                            href="{{ $item['full_url'] }}"
                            class="wb-gallery-trigger"
                            data-wb-gallery-full="{{ $item['full_url'] }}"
                            data-wb-gallery-alt="{{ $item['alt'] }}"
                            @if ($item['caption'] !== '') data-wb-gallery-caption="{{ $item['caption'] }}" @endif
                            @if ($item['meta'] !== '') data-wb-gallery-meta="{{ $item['meta'] }}" @endif
                            @if ($item['width']) data-wb-gallery-width="{{ $item['width'] }}" @endif
                            @if ($item['height']) data-wb-gallery-height="{{ $item['height'] }}" @endif
                        >
                            <img
                                src="{{ $item['thumbnail_url'] }}"
                                alt="{{ $item['alt'] }}"
                                class="wb-gallery-media"
                                @if ($item['width']) width="{{ $item['width'] }}" @endif
                                @if ($item['height']) height="{{ $item['height'] }}" @endif
                            >
                        </a>

                        @if ($item['caption'] !== '')
                            <figcaption class="wb-gallery-caption">{{ $item['caption'] }}</figcaption>
                        @endif

                        @if ($item['meta'] !== '')
                            <p class="wb-gallery-meta">{{ $item['meta'] }}</p>
                        @endif
                    </figure>
                @endforeach
            </div>
        </section>
    @endif
</div>

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
