@php
    $title = trim((string) ($block->title ?? ''));
    $introText = trim((string) ($block->subtitle ?? ''));
    $metaItems = $block->metaItems();
    $alignmentClass = $block->contentHeaderAlignmentClass();
    $headerClass = trim('wb-content-header '.($alignmentClass ?? ''));
@endphp

<header class="{{ $headerClass }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
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
