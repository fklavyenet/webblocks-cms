@php
    $label = $block->subtitle;
    $value = $block->title;
    $description = $block->content;
    $url = $block->url;
    $hasLabel = $label !== null && trim((string) $label) !== '';
    $hasValue = $value !== null && trim((string) $value) !== '';
    $hasDescription = $description !== null && trim((string) $description) !== '';
@endphp

<article class="wb-card">
    <div class="wb-card-body wb-stack wb-gap-2">
        @if ($hasLabel)
            <p class="wb-eyebrow">{{ $label }}</p>
        @endif

        @if ($hasValue)
            @if ($url)
                <a href="{{ $url }}" class="wb-no-decoration">
                    <strong class="wb-stat-value">{{ $value }}</strong>
                </a>
            @else
                <strong class="wb-stat-value">{{ $value }}</strong>
            @endif
        @endif

        @if ($hasDescription)
            <p class="wb-m-0">{{ $description }}</p>
        @endif
    </div>
</article>
