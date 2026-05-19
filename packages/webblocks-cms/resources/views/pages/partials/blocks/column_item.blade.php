@php
    $columnsVariant = $columnsVariant ?? null;
    $hasRenderableText = static fn ($value): bool => $value !== null && trim((string) $value) !== '';
    $title = $hasRenderableText($block->title) ? (string) $block->title : null;
    $subtitle = $hasRenderableText($block->subtitle) ? (string) $block->subtitle : null;
    $content = $hasRenderableText($block->content) ? (string) $block->content : null;
    $statValue = $subtitle ?? $title;
@endphp

@switch($columnsVariant)
    @case('plain')
        <div class="wb-stack wb-gap-2">
            @if ($title !== null)
                <strong>{{ $title }}</strong>
            @endif

            @if ($content !== null)
                <p class="wb-m-0">{{ $content }}</p>
            @endif
        </div>
        @break

    @case('stats')
        <div class="wb-stat">
            @if ($title !== null)
                <div class="wb-stat-label">{{ $title }}</div>
            @endif

            @if ($statValue !== null)
                <div class="wb-stat-value">{{ $statValue }}</div>
            @endif

            @if ($content !== null)
                <div class="wb-stat-delta">{{ $content }}</div>
            @endif
        </div>
        @break

    @case('cards')
    @default
        <div class="wb-card">
            <div class="wb-card-body wb-stack wb-gap-2">
                @if ($block->url)
                    <a href="{{ $block->url }}" class="wb-no-decoration">
                        <div class="wb-stack wb-gap-2">
                            @if ($title !== null)
                                <strong>{{ $title }}</strong>
                            @endif

                            @if ($content !== null)
                                <p class="wb-m-0">{{ $content }}</p>
                            @endif
                        </div>
                    </a>
                @else
                    <div class="wb-stack wb-gap-2">
                        @if ($title !== null)
                            <strong>{{ $title }}</strong>
                        @endif

                        @if ($content !== null)
                            <p class="wb-m-0">{{ $content }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
@endswitch
