@php
    $columnsVariant = $columnsVariant ?? null;
    $hasRenderableText = static fn ($value): bool => $value !== null && trim((string) $value) !== '';
    $title = $hasRenderableText($block->title) ? (string) $block->title : null;
    $subtitle = $hasRenderableText($block->subtitle) ? (string) $block->subtitle : null;
    $content = $hasRenderableText($block->content) ? (string) $block->content : null;
    // stats mapping: subtitle is the prominent value when present, and title
    // becomes the descriptor label; otherwise title itself is the value so the
    // stat never renders the same text as both label and value.
    if ($subtitle !== null) {
        $statLabel = $title;
        $statValue = $subtitle;
    } else {
        $statLabel = null;
        $statValue = $title;
    }
    $iconPresenter = app(\WebBlocks\Cms\Support\PublicRendering\PublicIconPresenter::class);
    $iconClass = $iconPresenter->iconClass($block->publicContentIconSlug(), 'content', $block->publicIconTone());
    $badgeLabel = $block->publicBadgeLabel();
    $badgeClass = $iconPresenter->badgeClass($block->publicBadgeTone());
@endphp

@php
    $renderIcon = fn (): string => $iconClass === null
        ? ''
        : '<i class="'.e($iconClass).'" aria-hidden="true"></i>';
@endphp

@switch($columnsVariant)
    @case('plain')
        <div class="wb-icon-card wb-items-start">
            {!! $renderIcon() !!}

            <div class="wb-stack wb-gap-2">
                @if ($badgeLabel !== null)
                    <span class="{{ $badgeClass }}">{{ $badgeLabel }}</span>
                @endif

                @if ($title !== null)
                    <strong>{{ $title }}</strong>
                @endif

                @if ($content !== null)
                    <p class="wb-m-0">{{ $content }}</p>
                @endif
            </div>
        </div>
        @break

    @case('stats')
        <div class="wb-stat">
            @if ($iconClass !== null || $badgeLabel !== null)
                <div class="wb-cluster wb-gap-2">
                    {!! $renderIcon() !!}

                    @if ($badgeLabel !== null)
                        <span class="{{ $badgeClass }}">{{ $badgeLabel }}</span>
                    @endif
                </div>
            @endif

            @if ($statLabel !== null)
                <div class="wb-stat-label">{{ $statLabel }}</div>
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
                        <div class="wb-icon-card wb-items-start">
                            {!! $renderIcon() !!}

                            <div class="wb-stack wb-gap-2">
                                @if ($badgeLabel !== null)
                                    <span class="{{ $badgeClass }}">{{ $badgeLabel }}</span>
                                @endif

                                @if ($title !== null)
                                    <strong>{{ $title }}</strong>
                                @endif

                                @if ($content !== null)
                                    <p class="wb-m-0">{{ $content }}</p>
                                @endif
                            </div>
                        </div>
                    </a>
                @else
                    <div class="wb-icon-card wb-items-start">
                        {!! $renderIcon() !!}

                        <div class="wb-stack wb-gap-2">
                            @if ($badgeLabel !== null)
                                <span class="{{ $badgeClass }}">{{ $badgeLabel }}</span>
                            @endif

                            @if ($title !== null)
                                <strong>{{ $title }}</strong>
                            @endif

                            @if ($content !== null)
                                <p class="wb-m-0">{{ $content }}</p>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
@endswitch
