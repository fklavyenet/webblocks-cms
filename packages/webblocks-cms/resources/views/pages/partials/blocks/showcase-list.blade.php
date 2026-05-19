@php
    use App\Models\Block;

    $settings = is_array($block->settings)
        ? $block->settings
        : (json_decode((string) $block->settings, true) ?: []);
    $items = collect($settings['items'] ?? []);
    $viewerId = 'wb-gallery-viewer-'.$block->id;
    $assetIds = $items
        ->flatMap(fn ($item) => collect($item['images'] ?? [])->pluck('asset_id'))
        ->filter()
        ->map(fn ($id) => (int) $id)
        ->values();
    $assets = $assetIds->isEmpty()
        ? collect()
        : \App\Models\Media::query()->whereIn('id', $assetIds)->get()->keyBy('id');
    $galleryItems = $items
        ->flatMap(function ($item) use ($assets) {
            return collect($item['images'] ?? [])->map(function ($image) use ($assets, $item) {
                $asset = $assets->get((int) ($image['asset_id'] ?? 0));
                $imageUrl = $asset?->url();

                if (! $imageUrl) {
                    return null;
                }

                $caption = trim((string) ($image['title'] ?? ''));
                $alt = trim((string) ($asset?->alt_text ?: ($image['title'] ?? ($item['title'] ?? 'Project image'))));

                return [
                    'thumbnail_url' => $imageUrl,
                    'full_url' => $imageUrl,
                    'alt' => $alt,
                    'caption' => $caption,
                    'meta' => $caption,
                    'width' => $asset?->width,
                    'height' => $asset?->height,
                ];
            });
        })
        ->filter()
        ->values();
@endphp

<section class="wb-stack wb-gap-6">
    @if ($block->title)
        <div class="wb-stack wb-gap-1">
            <h2>{{ $block->title }}</h2>
            @if ($block->subtitle)
                <p>{{ $block->subtitle }}</p>
            @endif
        </div>
    @endif

    @foreach ($items as $item)
        <article class="wb-card wb-card-muted wb-public-showcase-item">
            <div class="wb-card-body wb-stack wb-gap-4">
                <div class="wb-stack wb-gap-1">
                    <h3>{{ $item['title'] ?? 'Project' }}</h3>
                    @if (! empty($item['subtitle']))
                        <p>{{ $item['subtitle'] }}</p>
                    @endif
                </div>

                @if (! empty($item['images']) && is_array($item['images']))
                    <section class="wb-gallery" aria-label="{{ $item['title'] ?? 'Project screenshots' }}">
                        <div class="wb-gallery-grid">
                            @foreach ($item['images'] as $image)
                                @php
                                    $asset = $assets->get((int) ($image['asset_id'] ?? 0));
                                    $imageUrl = $asset?->url();
                                @endphp

                                @if ($imageUrl)
                                    <figure class="wb-gallery-item">
                                        <a
                                            href="{{ $imageUrl }}"
                                            class="wb-gallery-trigger"
                                            data-wb-gallery-target="#{{ $viewerId }}"
                                            data-wb-gallery-full="{{ $imageUrl }}"
                                            data-wb-gallery-alt="{{ $asset?->alt_text ?: ($image['title'] ?? ($item['title'] ?? 'Project image')) }}"
                                            @if (! empty($image['title'])) data-wb-gallery-caption="{{ $image['title'] }}" @endif
                                            @if ($asset?->width) data-wb-gallery-width="{{ $asset->width }}" @endif
                                            @if ($asset?->height) data-wb-gallery-height="{{ $asset->height }}" @endif
                                        >
                                            <img src="{{ $imageUrl }}" alt="{{ $asset?->alt_text ?: ($image['title'] ?? ($item['title'] ?? 'Project image')) }}" class="wb-gallery-media">
                                        </a>

                                        @if (! empty($image['title']))
                                            <figcaption class="wb-gallery-caption">{{ $image['title'] }}</figcaption>
                                        @endif
                                    </figure>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endif

                @php
                    $itemUrl = Block::safePublicUrl($item['url'] ?? null);
                @endphp

                @if ($itemUrl)
                    <a href="{{ $itemUrl }}" target="_blank" rel="noopener noreferrer" class="wb-link">{{ $item['url_label'] ?? $itemUrl }}</a>
                @endif
            </div>
        </article>
    @endforeach
</section>

@if ($galleryItems->isNotEmpty())
    @php
        $galleryViewerHtml = view('webblocks-cms::pages.partials.blocks.gallery-viewer', [
            'viewerId' => $viewerId,
            'galleryItems' => $galleryItems,
        ])->render();
        app(\WebBlocks\Cms\Support\Blocks\PublicOverlayRegistry::class)->push($galleryViewerHtml);
    @endphp
@endif
