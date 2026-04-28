@php
    $settings = is_array($block->settings)
        ? $block->settings
        : (json_decode((string) $block->settings, true) ?: []);
    $items = collect($settings['items'] ?? []);
    $assetIds = $items
        ->flatMap(fn ($item) => collect($item['images'] ?? [])->pluck('asset_id'))
        ->filter()
        ->map(fn ($id) => (int) $id)
        ->values();
    $assets = $assetIds->isEmpty()
        ? collect()
        : \App\Models\Asset::query()->whereIn('id', $assetIds)->get()->keyBy('id');
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
                                            data-wb-gallery-full="{{ $imageUrl }}"
                                            data-wb-gallery-alt="{{ $asset?->alt_text ?: ($image['title'] ?? ($item['title'] ?? 'Project image')) }}"
                                            @if (! empty($image['title'])) data-wb-gallery-caption="{{ $image['title'] }}" @endif
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

                @if (! empty($item['url']))
                    <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer" class="wb-link">{{ $item['url_label'] ?? $item['url'] }}</a>
                @endif
            </div>
        </article>
    @endforeach
</section>
