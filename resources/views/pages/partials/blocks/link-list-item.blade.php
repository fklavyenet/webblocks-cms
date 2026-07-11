@php
    $href = $block->linkListItemUrl();
    $title = $block->stringValueOrNull($block->title) ?? $block->translatedTextFieldValue('title');
    $meta = $block->stringValueOrNull($block->subtitle) ?? $block->translatedTextFieldValue('subtitle');
    $description = $block->stringValueOrNull($block->content) ?? $block->translatedTextFieldValue('content');
    $iconPresenter = app(\WebBlocks\Cms\Support\PublicRendering\PublicIconPresenter::class);
    $iconClass = $iconPresenter->iconClass($block->publicContentIconSlug(), 'content', $block->publicIconTone());
    $badgeLabel = $block->publicBadgeLabel();
    $badgeClass = $iconPresenter->badgeClass($block->publicBadgeTone());
@endphp

@if ($href !== null && $title !== null)
    <a href="{{ $href }}" class="wb-link-list-item">
        @if ($iconClass !== null)
            <i class="{{ $iconClass }}" aria-hidden="true"></i>
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
