@php
    $settings = is_array($block->settings)
        ? $block->settings
        : (json_decode((string) $block->settings, true) ?: []);
    $items = collect($settings['items'] ?? []);
    $assetIds = $items->pluck('asset_id')->filter()->map(fn ($id) => (int) $id)->values();
    $assets = $assetIds->isEmpty()
        ? collect()
        : \App\Models\Asset::query()->whereIn('id', $assetIds)->get()->keyBy('id');
    $gridClass = match (true) {
        $items->count() <= 1 => 'wb-stack wb-gap-3',
        $items->count() === 2 => 'wb-grid wb-grid-2',
        $items->count() === 3 => 'wb-grid wb-grid-3',
        default => 'wb-grid wb-grid-4',
    };
@endphp

<section class="wb-stack wb-gap-4">
    @if ($block->title || $block->subtitle)
        <div class="wb-stack wb-gap-1">
            @if ($block->title)
                <h2>{{ $block->title }}</h2>
            @endif
            @if ($block->subtitle)
                <p class="wb-text-muted">{{ $block->subtitle }}</p>
            @endif
        </div>
    @endif

    @if ($items->isNotEmpty())
        <div class="{{ $gridClass }}">
            @foreach ($items as $item)
                @php
                    $asset = $assets->get((int) ($item['asset_id'] ?? 0));
                    $assetUrl = $asset?->url();
                @endphp

                <div class="wb-card">
                    <div class="wb-card-body wb-stack wb-gap-2">
                        @if ($assetUrl)
                            <img src="{{ $assetUrl }}" alt="{{ $asset?->alt_text ?: ($item['title'] ?? 'Card image') }}">
                        @endif

                        @if (! empty($item['title']))
                            <strong>{{ $item['title'] }}</strong>
                        @endif

                        @if (! empty($item['content']))
                            <p class="wb-m-0">{{ $item['content'] }}</p>
                        @endif

                        @if (! empty($item['url']) && ! empty($item['url_label']))
                            <a href="{{ $item['url'] }}" class="wb-link">{{ $item['url_label'] }}</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
