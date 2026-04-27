@php
    $columnsVariant = $columnsVariant ?? null;
@endphp

@switch($columnsVariant)
    @case('plain')
        <div class="wb-stack wb-gap-2">
            @if ($block->title)
                <strong>{{ $block->title }}</strong>
            @endif

            @if ($block->content)
                <p class="wb-m-0">{{ $block->content }}</p>
            @endif
        </div>
        @break

    @case('stats')
        <div class="wb-stat">
            @if ($block->title)
                <div class="wb-stat-label">{{ $block->title }}</div>
            @endif

            <div class="wb-stat-value">{{ $block->subtitle ?: $block->title }}</div>

            @if ($block->content)
                <div class="wb-stat-delta">{{ $block->content }}</div>
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
                            @if ($block->title)
                                <strong>{{ $block->title }}</strong>
                            @endif

                            @if ($block->content)
                                <p class="wb-m-0">{{ $block->content }}</p>
                            @endif
                        </div>
                    </a>
                @else
                    <div class="wb-stack wb-gap-2">
                        @if ($block->title)
                            <strong>{{ $block->title }}</strong>
                        @endif

                        @if ($block->content)
                            <p class="wb-m-0">{{ $block->content }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
@endswitch
