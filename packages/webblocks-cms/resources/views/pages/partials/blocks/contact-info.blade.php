@php
    use WebBlocks\Cms\Models\Block;

    $settings = is_array($block->settings)
        ? $block->settings
        : (json_decode((string) $block->settings, true) ?: []);
    $items = collect($settings['items'] ?? []);
@endphp

<section class="wb-card wb-card-muted wb-public-contact-card">
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
            @php
                $itemUrl = Block::safePublicUrl($item['url'] ?? null);
            @endphp

            <div class="wb-stack wb-gap-1 wb-public-contact-meta">
                <strong>{{ $item['label'] ?? '' }}</strong>

                @if ($itemUrl)
                    <a href="{{ $itemUrl }}" @if (($item['target'] ?? null) === '_blank') target="_blank" rel="noopener noreferrer" @endif class="wb-link">
                        {{ $item['value'] ?? $itemUrl }}
                    </a>
                @else
                    <span>{{ $item['value'] ?? '' }}</span>
                @endif
            </div>
        @endforeach
    </div>
</section>
