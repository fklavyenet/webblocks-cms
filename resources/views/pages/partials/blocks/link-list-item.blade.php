@php
    $href = $block->linkListItemUrl();
    $title = $block->stringValueOrNull($block->title) ?? $block->translatedTextFieldValue('title');
    $meta = $block->stringValueOrNull($block->subtitle) ?? $block->translatedTextFieldValue('subtitle');
    $description = $block->stringValueOrNull($block->content) ?? $block->translatedTextFieldValue('content');
    $iconPresenter = app(\WebBlocks\Cms\Support\PublicRendering\PublicIconPresenter::class);
    $iconClass = $iconPresenter->iconClass($block->publicContentIconSlug(), $block->publicIconTone());
    $badgeLabel = $block->publicBadgeLabel();
    $badgeClass = $iconPresenter->badgeClass($block->publicBadgeTone());

    // A thumbnail and an icon both claim the row's single leading column, so an
    // uploaded thumbnail wins and the icon is dropped.
    $thumbnail = $block->asset;
    $thumbnailUrl = $thumbnail?->isImage()
        ? ($thumbnail->transformUrl('thumbnail') ?? $thumbnail->url())
        : null;

    if ($thumbnailUrl !== null) {
        $iconClass = null;
    }

    $hasLeadingVisual = $thumbnailUrl !== null || $iconClass !== null;
@endphp

@if ($href !== null && $title !== null)
    <a href="{{ $href }}" @class(['wb-link-list-item', 'wb-link-list-item--media' => $hasLeadingVisual])>
        @if ($thumbnailUrl !== null)
            <img src="{{ $thumbnailUrl }}" alt="{{ $thumbnail->alt_text ?: '' }}" class="wb-link-list-thumb" loading="lazy" decoding="async">
        @elseif ($iconClass !== null)
            <i class="{{ $iconClass }} wb-link-list-icon" aria-hidden="true"></i>
        @endif

        <div class="wb-link-list-main">
            <span class="wb-link-list-title">{{ $title }}</span>

            @if ($meta !== null)
                <span class="wb-link-list-meta">{{ $meta }}</span>
            @endif

            @if ($badgeLabel !== null)
                <span class="{{ $badgeClass }}">{{ $badgeLabel }}</span>
            @endif
        </div>

        @if ($description !== null)
            <div class="wb-link-list-desc">{{ $description }}</div>
        @endif
    </a>
@endif
