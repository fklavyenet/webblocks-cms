@php
    $title = trim((string) ($block->title ?? ''));
    $introText = trim((string) ($block->subtitle ?? ''));
    $metaItems = $block->metaItems();
    $alignmentClass = $block->contentHeaderAlignmentClass();
    $headerClass = collect(['wb-content-header', $alignmentClass, $block->publicBackgroundMediaClass()])->filter()->implode(' ');
    $backgroundStyle = $block->publicBackgroundMediaStyle();
    $iconPresenter = app(\WebBlocks\Cms\Support\PublicRendering\PublicIconPresenter::class);
    $iconClass = $iconPresenter->iconClass($block->publicContentIconSlug(), $block->publicIconTone());
    $badgeLabel = $block->publicBadgeLabel();
    $badgeClass = $iconPresenter->badgeClass($block->publicBadgeTone());

    // Only the first content_header rendered per request is the page's <h1>;
    // every later one (slider slides, additional sections) drops to <h2> so a
    // page never ships more than one h1. Request-scoped, safe under Octane.
    $wbHeadingIndex = (int) request()->attributes->get('_wb_content_header_count', 0);
    request()->attributes->set('_wb_content_header_count', $wbHeadingIndex + 1);
    $titleTag = $wbHeadingIndex === 0 ? 'h1' : 'h2';
@endphp

<header class="{{ $headerClass }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}"@if ($backgroundStyle !== null) style="{{ $backgroundStyle }}"@endif>
    @if ($iconClass !== null || $badgeLabel !== null)
        <div class="wb-cluster wb-gap-2">
            @if ($iconClass !== null)
                <i class="{{ $iconClass }}" aria-hidden="true"></i>
            @endif

            @if ($badgeLabel !== null)
                <span class="{{ $badgeClass }}">{{ $badgeLabel }}</span>
            @endif
        </div>
    @endif

    <{{ $titleTag }} class="wb-content-title">{{ $title }}</{{ $titleTag }}>

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
