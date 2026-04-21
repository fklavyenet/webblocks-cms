@php
    $settings = is_array($block->settings)
        ? $block->settings
        : (json_decode((string) $block->settings, true) ?: []);
    $items = collect($settings['items'] ?? []);
    $assetIds = $items->pluck('asset_id')->filter()->map(fn ($id) => (int) $id)->values();
    $assets = $assetIds->isEmpty()
        ? collect()
        : \App\Models\Asset::query()->whereIn('id', $assetIds)->get()->keyBy('id');
@endphp

<section class="wb-stack wb-gap-4 wb-public-card-grid">
    @if ($block->title)
        <div class="wb-stack wb-gap-1">
            <h2>{{ $block->title }}</h2>
            @if ($block->subtitle)
                <p>{{ $block->subtitle }}</p>
            @endif
        </div>
    @endif

    <div class="wb-grid wb-grid-3">
        @foreach ($items as $item)
            @php
                $asset = $assets->get((int) ($item['asset_id'] ?? 0));
                $assetUrl = $asset?->url();
            @endphp

            <article class="wb-card wb-card-muted wb-public-card-item">
                <div class="wb-card-body wb-stack wb-gap-2">
                    @if ($assetUrl)
                        <img src="{{ $assetUrl }}" alt="{{ $asset?->alt_text ?: ($item['title'] ?? 'Card image') }}">
                    @endif

                    @if (! empty($item['title']))
                        <h3>{{ $item['title'] }}</h3>
                    @endif

                    @if (! empty($item['content']))
                        <p>{{ $item['content'] }}</p>
                    @endif

                    @if (! empty($item['url']) && ! empty($item['url_label']))
                        <a href="{{ $item['url'] }}" class="wb-link">{{ $item['url_label'] }}</a>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>
