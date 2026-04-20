@php
    $settings = is_array($block->settings)
        ? $block->settings
        : (json_decode((string) $block->settings, true) ?: []);
    $items = collect($settings['items'] ?? []);
@endphp

<section class="wb-card wb-card-muted">
    <div class="wb-card-body wb-stack wb-gap-3">
        @if ($block->title)
            <div class="wb-stack wb-gap-1">
                <h2>{{ $block->title }}</h2>
                @if ($block->subtitle)
                    <p>{{ $block->subtitle }}</p>
                @endif
            </div>
        @endif

        @foreach ($items as $item)
            <div class="wb-stack wb-gap-1">
                <strong>{{ $item['label'] ?? '' }}</strong>

                @if (! empty($item['url']))
                    <a href="{{ $item['url'] }}" @if (! empty($item['target'])) target="{{ $item['target'] }}" rel="noopener noreferrer" @endif class="wb-link">
                        {{ $item['value'] ?? $item['url'] }}
                    </a>
                @else
                    <span>{{ $item['value'] ?? '' }}</span>
                @endif
            </div>
        @endforeach
    </div>
</section>
