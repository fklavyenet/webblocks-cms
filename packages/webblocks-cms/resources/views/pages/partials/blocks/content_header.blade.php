@php
    $title = trim((string) ($block->title ?? ''));
    $introText = trim((string) ($block->subtitle ?? ''));
    $metaItems = $block->metaItems();
    $alignmentClass = $block->contentHeaderAlignmentClass();
    $headerClass = trim('wb-content-header '.($alignmentClass ?? ''));
    $iconPresenter = app(\WebBlocks\Cms\Support\PublicRendering\PublicIconPresenter::class);
    $iconClass = $iconPresenter->iconClass($block->publicContentIconSlug(), 'content', $block->publicIconTone());
    $badgeLabel = $block->publicBadgeLabel();
    $badgeClass = $iconPresenter->badgeClass($block->publicBadgeTone());
@endphp

<header class="{{ $headerClass }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
    @if ($iconClass !== null || $badgeLabel !== null)
        <div class="wb-cms-public-kicker">
            @if ($iconClass !== null)
                <i class="{{ $iconClass }}" aria-hidden="true"></i>
            @endif

            @if ($badgeLabel !== null)
                <span class="{{ $badgeClass }}">{{ $badgeLabel }}</span>
            @endif
        </div>
    @endif

    <h1 class="wb-content-title">{{ $title }}</h1>

    @if ($introText !== '')
        <p class="wb-content-subtitle">{{ $introText }}</p>
    @endif

    @if ($metaItems->isNotEmpty())
        <div class="wb-content-meta">
            @foreach ($metaItems as $metaItem)
                <span>{{ $metaItem }}</span>

                @if (! $loop->last)
                    <span class="wb-content-meta-divider"></span>
                @endif
            @endforeach
        </div>
    @endif
</header>
